-- Run this in the Supabase SQL editor (or via `supabase db push`) on a fresh project.

create type user_role as enum ('admin','user');
create type user_status as enum ('pending','approved');
create type platform_type as enum ('meta','google');
create type metric_level as enum ('campaign','adset','ad');

create table users (
  id bigint generated always as identity primary key,
  name text not null,
  email text not null,
  password text,                    -- bcrypt hash (bcryptjs); random for Google-only users
  role user_role not null default 'user',
  status user_status not null default 'pending',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create unique index users_email_ci_uk on users (lower(email));

create table client_ad_accounts (
  id bigint generated always as identity primary key,
  platform platform_type not null,
  account_name text not null,
  account_id text not null,
  access_token text not null,
  api_key_or_refresh_token text,
  last_synced_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table daily_ad_metrics (
  id bigint generated always as identity primary key,
  client_ad_account_id bigint not null references client_ad_accounts(id) on delete cascade,
  level metric_level not null default 'campaign',
  date date not null,
  campaign_id text not null default '',
  campaign_name text,
  adset_id text not null default '',   -- NOT NULL default '' so ON CONFLICT dedupes correctly (NULL <> NULL in Postgres)
  adset_name text,
  ad_id text not null default '',
  ad_name text,
  spend numeric(12,2) not null default 0,
  impressions integer not null default 0,
  clicks integer not null default 0,
  reach integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (client_ad_account_id, level, date, campaign_id, adset_id, ad_id)
);
create index daily_ad_metrics_account_date_idx on daily_ad_metrics (client_ad_account_id, date);
create index daily_ad_metrics_account_level_date_idx on daily_ad_metrics (client_ad_account_id, level, date);

create table company_settings (
  id smallint primary key default 1 check (id = 1),   -- enforces single-row at DB level
  company_name text not null default 'Ads Analytics',
  tagline text,
  logo_path text,
  login_banner_path text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
insert into company_settings (id) values (1) on conflict do nothing;

-- RLS: default-deny on all tables. All real access goes through /api/* using the
-- service_role key, which bypasses RLS by design. This is a free defense-in-depth
-- net in case an anon/authenticated Supabase key is ever introduced client-side later.
alter table users enable row level security;
alter table client_ad_accounts enable row level security;
alter table daily_ad_metrics enable row level security;
alter table company_settings enable row level security;

-- Aggregation helpers used by /api/ads/stats.ts (Supabase-js doesn't do GROUP BY fluently).

create or replace function ads_stats_summary(
  p_account_id bigint, p_level metric_level, p_start date, p_end date
) returns table (
  total_spend numeric, total_impressions bigint, total_clicks bigint, total_reach bigint
) language sql stable as $$
  select
    coalesce(sum(spend), 0),
    coalesce(sum(impressions), 0),
    coalesce(sum(clicks), 0),
    coalesce(sum(reach), 0)
  from daily_ad_metrics
  where client_ad_account_id = p_account_id
    and level = p_level
    and date between p_start and p_end;
$$;

create or replace function ads_stats_daily(
  p_account_id bigint, p_level metric_level, p_start date, p_end date
) returns table (
  date date, campaign_id text, campaign_name text, adset_id text, adset_name text,
  ad_id text, ad_name text, spend numeric, impressions bigint, clicks bigint, reach bigint
) language sql stable as $$
  select date, campaign_id, campaign_name, adset_id, adset_name, ad_id, ad_name,
    sum(spend), sum(impressions), sum(clicks), sum(reach)
  from daily_ad_metrics
  where client_ad_account_id = p_account_id
    and level = p_level
    and date between p_start and p_end
  group by date, campaign_id, campaign_name, adset_id, adset_name, ad_id, ad_name
  order by date asc;
$$;

create or replace function ads_stats_trend(
  p_account_id bigint, p_level metric_level, p_start date, p_end date
) returns table (
  date date, spend numeric, impressions bigint, clicks bigint, reach bigint
) language sql stable as $$
  select date, sum(spend), sum(impressions), sum(clicks), sum(reach)
  from daily_ad_metrics
  where client_ad_account_id = p_account_id
    and level = p_level
    and date between p_start and p_end
  group by date
  order by date asc;
$$;

create or replace function ads_stats_breakdown(
  p_account_id bigint, p_level metric_level, p_start date, p_end date
) returns table (
  entity_id text, entity_name text, spend numeric, impressions bigint, clicks bigint
) language sql stable as $$
  select
    case p_level when 'campaign' then campaign_id when 'adset' then adset_id else ad_id end,
    case p_level when 'campaign' then campaign_name when 'adset' then adset_name else ad_name end,
    sum(spend), sum(impressions), sum(clicks)
  from daily_ad_metrics
  where client_ad_account_id = p_account_id
    and level = p_level
    and date between p_start and p_end
  group by 1, 2
  order by sum(spend) desc
  limit 10;
$$;
