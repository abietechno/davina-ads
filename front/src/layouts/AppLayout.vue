<script setup>
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDarkMode } from '@/composables/useDarkMode'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { BarChart3, Home, Settings, LogOut, Sun, Moon, Menu } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { isDark, toggle: toggleDark } = useDarkMode()

const navItems = [
  { to: '/', label: 'Dashboard', icon: Home },
  { to: '/settings', label: 'Settings', icon: Settings },
]

function isActive(path) {
  return route.path === path
}

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <div class="relative min-h-screen bg-background transition-colors duration-200">

      <!-- ════════════════════════════════════════════ -->
      <!-- DESKTOP SIDEBAR — Threads style              -->
      <!-- ════════════════════════════════════════════ -->
      <aside class="fixed inset-y-0 left-0 z-40 hidden w-[76px] flex-col items-center bg-background py-5 md:flex">
        <!-- Logo — outlined style like Threads @ icon -->
        <router-link
          to="/"
          class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl text-foreground transition-all hover:scale-110"
        >
          <BarChart3 class="h-7 w-7" :stroke-width="1.5" />
        </router-link>

        <!-- Nav icons -->
        <nav class="flex flex-1 flex-col items-center gap-1">
          <Tooltip v-for="item in navItems" :key="item.to">
            <TooltipTrigger as-child>
              <router-link
                :to="item.to"
                :class="[
                  'group flex h-12 w-12 items-center justify-center rounded-2xl transition-all duration-200',
                  isActive(item.to)
                    ? 'text-foreground'
                    : 'text-muted-foreground hover:text-foreground hover:bg-accent',
                ]"
              >
                <component
                  :is="item.icon"
                  class="h-[26px] w-[26px] transition-all"
                  :stroke-width="isActive(item.to) ? 2.5 : 1.5"
                />
              </router-link>
            </TooltipTrigger>
            <TooltipContent side="right" :side-offset="8" class="rounded-xl text-xs">
              {{ item.label }}
            </TooltipContent>
          </Tooltip>
        </nav>

        <!-- Bottom actions -->
        <div class="flex flex-col items-center gap-1">
          <!-- Dark / Light toggle -->
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                class="flex h-12 w-12 items-center justify-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground hover:bg-accent"
                @click="toggleDark"
              >
                <Moon v-if="!isDark" class="h-[26px] w-[26px]" :stroke-width="1.5" />
                <Sun v-else class="h-[26px] w-[26px]" :stroke-width="1.5" />
              </button>
            </TooltipTrigger>
            <TooltipContent side="right" :side-offset="8" class="rounded-xl text-xs">
              {{ isDark ? 'Light mode' : 'Dark mode' }}
            </TooltipContent>
          </Tooltip>

          <!-- Logout -->
          <Tooltip>
            <TooltipTrigger as-child>
              <button
                class="flex h-12 w-12 items-center justify-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground hover:bg-accent"
                @click="handleLogout"
              >
                <LogOut class="h-[26px] w-[26px]" :stroke-width="1.5" />
              </button>
            </TooltipTrigger>
            <TooltipContent side="right" :side-offset="8" class="rounded-xl text-xs">
              Logout
            </TooltipContent>
          </Tooltip>

          <!-- Menu / More — like Threads bottom hamburger -->
          <div class="mt-2 flex h-12 w-12 items-center justify-center">
            <Menu class="h-6 w-6 text-muted-foreground" :stroke-width="1.5" />
          </div>
        </div>
      </aside>

      <!-- ════════════════════════════════════════════ -->
      <!-- MAIN CONTENT — centered column               -->
      <!-- ════════════════════════════════════════════ -->
      <div class="md:pl-[76px]">
        <div class="mx-auto max-w-5xl min-h-screen border-x border-border/50 pb-20 md:pb-0">
          <slot />
        </div>
      </div>

      <!-- ════════════════════════════════════════════ -->
      <!-- MOBILE BOTTOM NAV                             -->
      <!-- ════════════════════════════════════════════ -->
      <nav class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-border/50 bg-background pb-[env(safe-area-inset-bottom)] md:hidden">
        <router-link
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :class="[
            'flex flex-1 flex-col items-center py-3 transition-colors',
            isActive(item.to) ? 'text-foreground' : 'text-muted-foreground',
          ]"
        >
          <component
            :is="item.icon"
            class="h-6 w-6"
            :stroke-width="isActive(item.to) ? 2.5 : 1.5"
          />
        </router-link>

        <button
          class="flex flex-1 flex-col items-center py-3 text-muted-foreground transition-colors"
          @click="toggleDark"
        >
          <Moon v-if="!isDark" class="h-6 w-6" :stroke-width="1.5" />
          <Sun v-else class="h-6 w-6" :stroke-width="1.5" />
        </button>

        <button
          class="flex flex-1 flex-col items-center py-3 text-muted-foreground transition-colors"
          @click="handleLogout"
        >
          <LogOut class="h-6 w-6" :stroke-width="1.5" />
        </button>
      </nav>
    </div>
  </TooltipProvider>
</template>
