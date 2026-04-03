import { ref, watch } from 'vue'

// Default to dark if no preference saved
const saved = localStorage.getItem('theme')
const isDark = ref(saved ? saved === 'dark' : true)

function applyTheme() {
  document.documentElement.classList.toggle('dark', isDark.value)
}

// Apply on load
applyTheme()

watch(isDark, () => {
  localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
  applyTheme()
})

export function useDarkMode() {
  function toggle() {
    isDark.value = !isDark.value
  }

  return { isDark, toggle }
}
