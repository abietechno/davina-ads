<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDarkMode } from '@/composables/useDarkMode'
import api from '@/api/axios'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { BarChart3, Loader2, Sun, Moon } from 'lucide-vue-next'

const router = useRouter()
const auth = useAuthStore()
const { isDark, toggle: toggleDark } = useDarkMode()

const form = reactive({ email: '', password: '' })
const loading = ref(false)
const errorMessage = ref('')

// Company branding from API
const branding = reactive({
  company_name: 'Ads Analytics',
  tagline: 'Multi-Platform Ads Dashboard',
  logo_url: null,
  login_banner_url: null,
})

onMounted(async () => {
  try {
    const { data } = await api.get('/company-settings')
    if (data.data) {
      branding.company_name = data.data.company_name || 'Ads Analytics'
      branding.tagline = data.data.tagline || ''
      branding.logo_url = data.data.logo_url
      branding.login_banner_url = data.data.login_banner_url
    }
  } catch {
    // Use defaults
  }
})

async function handleLogin() {
  errorMessage.value = ''
  loading.value = true
  try {
    await auth.login(form.email, form.password)
    router.push('/')
  } catch (err) {
    if (err.response?.status === 422) {
      errorMessage.value = Object.values(err.response.data.errors).flat()[0]
    } else {
      errorMessage.value = err.response?.data?.message || 'Something went wrong.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen transition-colors duration-300">
    <!-- ════════════════════════════════════════════ -->
    <!-- LEFT: Banner Side                            -->
    <!-- ════════════════════════════════════════════ -->
    <div class="relative hidden w-1/2 lg:block xl:w-[55%]">
      <!-- Background image or gradient fallback -->
      <div class="absolute inset-0">
        <img
          v-if="branding.login_banner_url"
          :src="branding.login_banner_url"
          alt="Banner"
          class="h-full w-full object-cover"
        />
        <div
          v-else
          class="h-full w-full bg-gradient-to-br from-blue-600 via-blue-500 to-indigo-600"
        />
      </div>

      <!-- Overlay -->
      <div class="absolute inset-0 bg-black/30" />

      <!-- Content on top of banner -->
      <div class="relative flex h-full flex-col justify-end p-10 xl:p-14">
        <!-- Logo on banner -->
        <div v-if="branding.logo_url" class="absolute top-10 left-10 xl:top-14 xl:left-14">
          <img :src="branding.logo_url" alt="Logo" class="h-10 object-contain brightness-0 invert" />
        </div>

        <div class="max-w-lg">
          <p class="text-sm font-medium text-white/80 tracking-wide uppercase">
            {{ branding.tagline }}
          </p>
          <h2 class="mt-2 font-display text-4xl font-extrabold text-white xl:text-5xl leading-tight">
            {{ branding.company_name }}
          </h2>
          <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2">
            <div class="flex items-center gap-2 text-sm text-white/90">
              <svg class="h-4 w-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              Meta Ads Analytics
            </div>
            <div class="flex items-center gap-2 text-sm text-white/90">
              <svg class="h-4 w-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              Google Ads Analytics
            </div>
            <div class="flex items-center gap-2 text-sm text-white/90">
              <svg class="h-4 w-4 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              Multi-Client Dashboard
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════ -->
    <!-- RIGHT: Login Form                            -->
    <!-- ════════════════════════════════════════════ -->
    <div class="flex w-full flex-col bg-background lg:w-1/2 xl:w-[45%]">
      <!-- Theme toggle -->
      <div class="flex justify-end p-4">
        <button
          class="flex h-10 w-10 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
          @click="toggleDark"
        >
          <Moon v-if="!isDark" class="h-5 w-5" />
          <Sun v-else class="h-5 w-5" />
        </button>
      </div>

      <!-- Form centered -->
      <div class="flex flex-1 flex-col items-center justify-center px-6 pb-10 sm:px-12">
        <div class="w-full max-w-sm">
          <!-- Logo / Brand -->
          <div class="mb-8">
            <div v-if="branding.logo_url" class="mb-4">
              <img :src="branding.logo_url" alt="Logo" class="h-12 object-contain" />
            </div>
            <div v-else class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
              <BarChart3 class="h-6 w-6" />
            </div>
            <h1 class="font-display text-2xl font-bold tracking-tight">Selamat Datang</h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Silakan masuk ke akun Anda untuk melanjutkan ke dashboard.
            </p>
          </div>

          <!-- Error -->
          <div
            v-if="errorMessage"
            class="mb-4 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          >
            {{ errorMessage }}
          </div>

          <form @submit.prevent="handleLogin" class="space-y-4">
            <div class="space-y-2">
              <Label for="email" class="text-xs">Username</Label>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="admin@admin.com"
                class="rounded-xl h-11"
                autofocus
                required
              />
            </div>

            <div class="space-y-2">
              <Label for="password" class="text-xs">Password</Label>
              <Input
                id="password"
                v-model="form.password"
                type="password"
                placeholder="••••••••"
                class="rounded-xl h-11"
                required
              />
            </div>

            <Button type="submit" class="w-full rounded-xl h-11 text-sm font-semibold" :disabled="loading">
              <Loader2 v-if="loading" class="mr-2 h-4 w-4 animate-spin" />
              {{ loading ? 'Signing in...' : 'Login' }}
            </Button>
          </form>

          <!-- Mobile: company tagline -->
          <p class="mt-8 text-center text-xs text-muted-foreground lg:hidden">
            {{ branding.company_name }} &middot; {{ branding.tagline }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
