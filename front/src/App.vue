<script setup>
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import { Toaster } from '@/components/ui/sonner'

const auth = useAuthStore()
</script>

<template>
  <div class="min-h-screen bg-background">
    <!-- Authenticated: use AppLayout -->
    <AppLayout v-if="auth.isLoggedIn">
      <RouterView />
    </AppLayout>

    <!-- Guest: render view directly (Login) -->
    <RouterView v-else />

    <Toaster richColors position="top-center" />
  </div>
</template>
