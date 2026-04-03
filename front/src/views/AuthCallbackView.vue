<script setup>
import { onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

onMounted(() => {
  const token = route.query.token
  const name = route.query.name
  const error = route.query.error

  if (error) {
    router.push('/login?error=' + error)
    return
  }

  if (token) {
    // Save token and user info
    localStorage.setItem('auth_token', token)
    localStorage.setItem('auth_user', JSON.stringify({ name: name || 'User' }))
    auth.token = token
    auth.user = { name: name || 'User' }

    // Fetch full user data then redirect
    auth.fetchUser().then(() => {
      router.push('/')
    })
  } else {
    router.push('/login')
  }
})
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-background">
    <div class="text-center">
      <div class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground border-t-foreground" />
      <p class="text-sm text-muted-foreground">Signing you in...</p>
    </div>
  </div>
</template>
