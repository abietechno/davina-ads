import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('auth_user') || 'null'))
  const token = ref(localStorage.getItem('auth_token') || '')

  const isLoggedIn = computed(() => !!token.value)

  async function login(email, password) {
    const { data } = await api.post('/login', { email, password })

    token.value = data.data.token
    user.value = data.data.user

    localStorage.setItem('auth_token', data.data.token)
    localStorage.setItem('auth_user', JSON.stringify(data.data.user))

    return data
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/me')
      user.value = data.data
      localStorage.setItem('auth_user', JSON.stringify(data.data))
    } catch {
      logout()
    }
  }

  async function logout() {
    try {
      if (token.value) {
        await api.post('/logout')
      }
    } catch {
      // ignore
    } finally {
      token.value = ''
      user.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
    }
  }

  return { user, token, isLoggedIn, login, fetchUser, logout }
})
