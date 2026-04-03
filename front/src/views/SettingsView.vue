<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api/axios'
import { toast } from 'vue-sonner'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Loader2, Link2, Building2, Users, Upload, Trash2, ImagePlus, Pencil } from 'lucide-vue-next'

// ══════════════════════════════════════
// AD ACCOUNTS TAB
// ══════════════════════════════════════
const accountForm = reactive({
  platform: '',
  account_name: '',
  account_id: '',
  access_token: '',
})
const savedAccounts = ref([])
const loadingAccounts = ref(true)
const submittingAccount = ref(false)

// Edit state
const editSheetOpen = ref(false)
const editingAccount = ref(null)
const editForm = reactive({
  account_name: '',
  account_id: '',
  access_token: '',
  api_key_or_refresh_token: '',
})
const submittingEdit = ref(false)

async function fetchAccounts() {
  loadingAccounts.value = true
  try {
    const { data } = await api.get('/client-ad-accounts')
    savedAccounts.value = data.data
  } catch (err) {
    toast.error('Failed to load accounts')
  } finally {
    loadingAccounts.value = false
  }
}

async function handleAddAccount() {
  const f = accountForm
  if (!f.platform || !f.account_name || !f.account_id || !f.access_token) {
    toast.error('Please fill in all fields')
    return
  }
  submittingAccount.value = true
  try {
    const { data } = await api.post('/client-ad-accounts', { ...f })
    savedAccounts.value.push(data.data)
    toast.success('Account connected')
    Object.assign(accountForm, { platform: '', account_name: '', account_id: '', access_token: '' })
  } catch (err) {
    if (err.response?.status === 422) {
      toast.error('Validation error', { description: Object.values(err.response.data.errors).flat()[0] })
    } else {
      toast.error('Failed to save account')
    }
  } finally {
    submittingAccount.value = false
  }
}

function openEditSheet(account) {
  editingAccount.value = account
  editForm.account_name = account.account_name
  editForm.account_id = account.account_id
  editForm.access_token = ''
  editForm.api_key_or_refresh_token = ''
  editSheetOpen.value = true
}

async function handleEditAccount() {
  if (!editForm.account_name || !editForm.account_id) {
    toast.error('Name and ID are required')
    return
  }
  submittingEdit.value = true
  try {
    const payload = {
      account_name: editForm.account_name,
      account_id: editForm.account_id,
    }
    if (editForm.access_token) payload.access_token = editForm.access_token
    if (editForm.api_key_or_refresh_token) payload.api_key_or_refresh_token = editForm.api_key_or_refresh_token

    const { data } = await api.put(`/client-ad-accounts/${editingAccount.value.id}`, payload)

    // Update local list
    const idx = savedAccounts.value.findIndex((a) => a.id === editingAccount.value.id)
    if (idx !== -1) savedAccounts.value[idx] = data.data

    toast.success('Account updated')
    editSheetOpen.value = false
  } catch (err) {
    if (err.response?.status === 422) {
      toast.error('Validation error', { description: Object.values(err.response.data.errors).flat()[0] })
    } else {
      toast.error('Failed to update account')
    }
  } finally {
    submittingEdit.value = false
  }
}

async function handleDeleteAccount(account) {
  if (!confirm(`Delete "${account.account_name}"? All synced metrics for this account will also be deleted.`)) return
  try {
    await api.delete(`/client-ad-accounts/${account.id}`)
    savedAccounts.value = savedAccounts.value.filter((a) => a.id !== account.id)
    toast.success('Account deleted')
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to delete account')
  }
}

// ══════════════════════════════════════
// COMPANY TAB
// ══════════════════════════════════════
const companyForm = reactive({
  company_name: '',
  tagline: '',
})
const companySettings = ref(null)
const loadingCompany = ref(true)
const submittingCompany = ref(false)
const logoPreview = ref(null)
const bannerPreview = ref(null)
const logoFile = ref(null)
const bannerFile = ref(null)

async function fetchCompanySettings() {
  loadingCompany.value = true
  try {
    const { data } = await api.get('/company-settings')
    companySettings.value = data.data
    companyForm.company_name = data.data.company_name || ''
    companyForm.tagline = data.data.tagline || ''
    logoPreview.value = data.data.logo_url
    bannerPreview.value = data.data.login_banner_url
  } catch (err) {
    toast.error('Failed to load company settings')
  } finally {
    loadingCompany.value = false
  }
}

function handleLogoSelect(e) {
  const file = e.target.files[0]
  if (!file) return
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
}

function handleBannerSelect(e) {
  const file = e.target.files[0]
  if (!file) return
  bannerFile.value = file
  bannerPreview.value = URL.createObjectURL(file)
}

async function handleSaveCompany() {
  submittingCompany.value = true
  try {
    const formData = new FormData()
    formData.append('company_name', companyForm.company_name)
    formData.append('tagline', companyForm.tagline)
    if (logoFile.value) formData.append('logo', logoFile.value)
    if (bannerFile.value) formData.append('login_banner', bannerFile.value)

    const { data } = await api.post('/company-settings', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    companySettings.value = data.data
    logoPreview.value = data.data.logo_url
    bannerPreview.value = data.data.login_banner_url
    logoFile.value = null
    bannerFile.value = null
    toast.success('Company settings saved')
  } catch (err) {
    toast.error('Failed to save settings')
  } finally {
    submittingCompany.value = false
  }
}

// ══════════════════════════════════════
// USERS TAB
// ══════════════════════════════════════
const userForm = reactive({ name: '', email: '', password: '' })
const users = ref([])
const loadingUsers = ref(true)
const submittingUser = ref(false)

// Edit user state
const editUserSheetOpen = ref(false)
const editingUser = ref(null)
const editUserForm = reactive({ name: '', email: '', password: '' })
const submittingEditUser = ref(false)

async function fetchUsers() {
  loadingUsers.value = true
  try {
    const { data } = await api.get('/users')
    users.value = data.data
  } catch (err) {
    toast.error('Failed to load users')
  } finally {
    loadingUsers.value = false
  }
}

async function handleAddUser() {
  if (!userForm.name || !userForm.email || !userForm.password) {
    toast.error('Please fill in all fields')
    return
  }
  submittingUser.value = true
  try {
    const { data } = await api.post('/users', { ...userForm })
    users.value.unshift(data.data)
    toast.success('User created')
    Object.assign(userForm, { name: '', email: '', password: '' })
  } catch (err) {
    if (err.response?.status === 422) {
      toast.error('Validation error', { description: Object.values(err.response.data.errors).flat()[0] })
    } else {
      toast.error('Failed to create user')
    }
  } finally {
    submittingUser.value = false
  }
}

function openEditUserSheet(user) {
  editingUser.value = user
  editUserForm.name = user.name
  editUserForm.email = user.email
  editUserForm.password = ''
  editUserSheetOpen.value = true
}

async function handleEditUser() {
  if (!editUserForm.name || !editUserForm.email) {
    toast.error('Name and email are required')
    return
  }
  submittingEditUser.value = true
  try {
    const payload = {
      name: editUserForm.name,
      email: editUserForm.email,
    }
    if (editUserForm.password) payload.password = editUserForm.password

    const { data } = await api.put(`/users/${editingUser.value.id}`, payload)
    const idx = users.value.findIndex((u) => u.id === editingUser.value.id)
    if (idx !== -1) users.value[idx] = data.data
    toast.success('User updated')
    editUserSheetOpen.value = false
  } catch (err) {
    if (err.response?.status === 422) {
      toast.error('Validation error', { description: Object.values(err.response.data.errors).flat()[0] })
    } else {
      toast.error('Failed to update user')
    }
  } finally {
    submittingEditUser.value = false
  }
}

async function handleDeleteUser(user) {
  if (!confirm(`Delete user "${user.name}"?`)) return
  try {
    await api.delete(`/users/${user.id}`)
    users.value = users.value.filter((u) => u.id !== user.id)
    toast.success('User deleted')
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to delete user')
  }
}

onMounted(() => {
  fetchAccounts()
  fetchCompanySettings()
  fetchUsers()
})
</script>

<template>
  <div class="relative">
    <!-- Sticky header -->
    <div class="sticky top-0 z-10 border-b border-border/40 bg-background/80 backdrop-blur-xl">
      <div class="px-4 py-4 sm:px-6">
        <h1 class="font-display text-xl font-bold tracking-tight sm:text-2xl">Settings</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">Manage accounts, branding, and users.</p>
      </div>
    </div>

    <div class="px-4 py-6 sm:px-6">
      <Tabs default-value="accounts">
        <TabsList class="mb-6 w-full grid grid-cols-3 rounded-xl bg-muted/60">
          <TabsTrigger value="accounts" class="rounded-lg gap-1.5 text-xs sm:text-sm">
            <Link2 class="h-3.5 w-3.5 hidden sm:block" />
            Accounts
          </TabsTrigger>
          <TabsTrigger value="company" class="rounded-lg gap-1.5 text-xs sm:text-sm">
            <Building2 class="h-3.5 w-3.5 hidden sm:block" />
            Company
          </TabsTrigger>
          <TabsTrigger value="users" class="rounded-lg gap-1.5 text-xs sm:text-sm">
            <Users class="h-3.5 w-3.5 hidden sm:block" />
            Users
          </TabsTrigger>
        </TabsList>

        <!-- ═══════════════════════════════ -->
        <!-- TAB: Ad Accounts                -->
        <!-- ═══════════════════════════════ -->
        <TabsContent value="accounts" class="space-y-5">
          <Card>
            <CardHeader class="px-4 pt-4 pb-3">
              <CardTitle class="font-display text-base font-semibold">Connect Account</CardTitle>
              <CardDescription class="text-sm">Add Meta Ads or Google Ads credentials.</CardDescription>
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <form @submit.prevent="handleAddAccount" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                  <div class="space-y-2">
                    <Label class="text-xs">Platform</Label>
                    <Select v-model="accountForm.platform">
                      <SelectTrigger class="rounded-xl"><SelectValue placeholder="Select platform" /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="meta">Meta Ads</SelectItem>
                        <SelectItem value="google">Google Ads</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs">Account Name</Label>
                    <Input v-model="accountForm.account_name" placeholder="e.g. My Client — Main" class="rounded-xl" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs">Account ID</Label>
                    <Input v-model="accountForm.account_id" :placeholder="accountForm.platform === 'google' ? '123-456-7890' : 'act_123456789'" class="rounded-xl" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs">{{ accountForm.platform === 'google' ? 'API Key / Refresh Token' : 'Access Token' }}</Label>
                    <Input v-model="accountForm.access_token" type="password" placeholder="Paste token" class="rounded-xl" />
                  </div>
                </div>
                <div class="flex justify-end">
                  <Button type="submit" :disabled="submittingAccount" class="rounded-xl">
                    <Loader2 v-if="submittingAccount" class="mr-2 h-4 w-4 animate-spin" />
                    {{ submittingAccount ? 'Saving...' : 'Save Account' }}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="px-4 pt-4 pb-3">
              <CardTitle class="font-display text-base font-semibold">Connected Accounts</CardTitle>
            </CardHeader>
            <CardContent class="px-0 pb-0">
              <div v-if="loadingAccounts" class="space-y-3 px-4 pb-4">
                <Skeleton class="h-10 w-full rounded-xl" />
                <Skeleton class="h-10 w-full rounded-xl" />
              </div>
              <div v-else-if="savedAccounts.length === 0" class="py-14 text-center text-sm text-muted-foreground">
                No accounts connected yet.
              </div>
              <div v-else class="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow class="border-border/40 hover:bg-transparent">
                      <TableHead class="pl-4">Platform</TableHead>
                      <TableHead>Name</TableHead>
                      <TableHead class="hidden sm:table-cell">ID</TableHead>
                      <TableHead class="pr-4 text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="a in savedAccounts" :key="a.id" class="border-border/40">
                      <TableCell class="pl-4">
                        <Badge :variant="a.platform === 'meta' ? 'default' : 'secondary'" class="rounded-lg text-xs">
                          {{ a.platform === 'meta' ? 'Meta' : 'Google' }}
                        </Badge>
                      </TableCell>
                      <TableCell class="font-medium text-sm">{{ a.account_name }}</TableCell>
                      <TableCell class="font-mono text-xs text-muted-foreground hidden sm:table-cell">{{ a.account_id }}</TableCell>
                      <TableCell class="pr-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
                            @click="openEditSheet(a)"
                          >
                            <Pencil class="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
                            @click="handleDeleteAccount(a)"
                          >
                            <Trash2 class="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- ═══════════════════════════════ -->
        <!-- TAB: Company                    -->
        <!-- ═══════════════════════════════ -->
        <TabsContent value="company" class="space-y-5">
          <Card>
            <CardHeader class="px-4 pt-4 pb-3">
              <CardTitle class="font-display text-base font-semibold">Company Info</CardTitle>
              <CardDescription class="text-sm">This will appear on your login page.</CardDescription>
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <div v-if="loadingCompany" class="space-y-4">
                <Skeleton class="h-10 w-full rounded-xl" />
                <Skeleton class="h-10 w-full rounded-xl" />
                <Skeleton class="h-24 w-full rounded-xl" />
              </div>
              <form v-else @submit.prevent="handleSaveCompany" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                  <div class="space-y-2">
                    <Label class="text-xs">Company Name</Label>
                    <Input v-model="companyForm.company_name" placeholder="Your Company" class="rounded-xl" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs">Tagline</Label>
                    <Input v-model="companyForm.tagline" placeholder="Multi-Platform Ads Dashboard" class="rounded-xl" />
                  </div>
                </div>

                <!-- Logo Upload -->
                <div class="space-y-2">
                  <Label class="text-xs">Company Logo</Label>
                  <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-border bg-muted/40">
                      <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="h-full w-full object-contain" />
                      <Upload v-else class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <div>
                      <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium transition-colors hover:bg-muted">
                        <ImagePlus class="h-3.5 w-3.5" />
                        Choose Logo
                        <input type="file" accept="image/*" class="sr-only" @change="handleLogoSelect" />
                      </label>
                      <p class="mt-1 text-[10px] text-muted-foreground">PNG, JPG, SVG. Max 2MB.</p>
                    </div>
                  </div>
                </div>

                <!-- Banner Upload -->
                <div class="space-y-2">
                  <Label class="text-xs">Login Banner</Label>
                  <div class="relative overflow-hidden rounded-2xl border border-dashed border-border bg-muted/40">
                    <div class="aspect-[16/9] sm:aspect-[21/9]">
                      <img v-if="bannerPreview" :src="bannerPreview" alt="Banner" class="h-full w-full object-cover" />
                      <div v-else class="flex h-full items-center justify-center">
                        <div class="text-center">
                          <ImagePlus class="mx-auto h-8 w-8 text-muted-foreground" />
                          <p class="mt-2 text-xs text-muted-foreground">No banner uploaded</p>
                        </div>
                      </div>
                    </div>
                    <label class="absolute bottom-3 right-3 inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background/90 backdrop-blur px-3 py-2 text-xs font-medium transition-colors hover:bg-muted">
                      <Upload class="h-3.5 w-3.5" />
                      Upload Banner
                      <input type="file" accept="image/*" class="sr-only" @change="handleBannerSelect" />
                    </label>
                  </div>
                  <p class="text-[10px] text-muted-foreground">Recommended: 1920x1080. Max 5MB.</p>
                </div>

                <div class="flex justify-end">
                  <Button type="submit" :disabled="submittingCompany" class="rounded-xl">
                    <Loader2 v-if="submittingCompany" class="mr-2 h-4 w-4 animate-spin" />
                    {{ submittingCompany ? 'Saving...' : 'Save Settings' }}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- ═══════════════════════════════ -->
        <!-- TAB: Users                      -->
        <!-- ═══════════════════════════════ -->
        <TabsContent value="users" class="space-y-5">
          <Card>
            <CardHeader class="px-4 pt-4 pb-3">
              <CardTitle class="font-display text-base font-semibold">Add User</CardTitle>
              <CardDescription class="text-sm">Create a new user account for the dashboard.</CardDescription>
            </CardHeader>
            <CardContent class="px-4 pb-4">
              <form @submit.prevent="handleAddUser" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                  <div class="space-y-2">
                    <Label class="text-xs">Full Name</Label>
                    <Input v-model="userForm.name" placeholder="John Doe" class="rounded-xl" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs">Email</Label>
                    <Input v-model="userForm.email" type="email" placeholder="john@example.com" class="rounded-xl" />
                  </div>
                  <div class="space-y-2">
                    <Label class="text-xs">Password</Label>
                    <Input v-model="userForm.password" type="password" placeholder="Min 6 characters" class="rounded-xl" />
                  </div>
                </div>
                <div class="flex justify-end">
                  <Button type="submit" :disabled="submittingUser" class="rounded-xl">
                    <Loader2 v-if="submittingUser" class="mr-2 h-4 w-4 animate-spin" />
                    {{ submittingUser ? 'Creating...' : 'Create User' }}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="px-4 pt-4 pb-3">
              <CardTitle class="font-display text-base font-semibold">All Users</CardTitle>
            </CardHeader>
            <CardContent class="px-0 pb-0">
              <div v-if="loadingUsers" class="space-y-3 px-4 pb-4">
                <Skeleton class="h-10 w-full rounded-xl" />
                <Skeleton class="h-10 w-full rounded-xl" />
              </div>
              <div v-else-if="users.length === 0" class="py-14 text-center text-sm text-muted-foreground">
                No users found.
              </div>
              <div v-else class="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow class="border-border/40 hover:bg-transparent">
                      <TableHead class="pl-4">Name</TableHead>
                      <TableHead>Email</TableHead>
                      <TableHead class="hidden sm:table-cell">Created</TableHead>
                      <TableHead class="pr-4 text-right w-16"></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="u in users" :key="u.id" class="border-border/40">
                      <TableCell class="pl-4">
                        <div class="flex items-center gap-2.5">
                          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                            {{ u.name.charAt(0) }}
                          </div>
                          <span class="font-medium text-sm">{{ u.name }}</span>
                        </div>
                      </TableCell>
                      <TableCell class="text-sm text-muted-foreground">{{ u.email }}</TableCell>
                      <TableCell class="text-xs text-muted-foreground hidden sm:table-cell">{{ new Date(u.created_at).toLocaleDateString() }}</TableCell>
                      <TableCell class="pr-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
                            @click="openEditUserSheet(u)"
                          >
                            <Pencil class="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
                            @click="handleDeleteUser(u)"
                          >
                            <Trash2 class="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>

    <!-- ═══════════════════════════════════════ -->
    <!-- EDIT ACCOUNT SHEET                      -->
    <!-- ═══════════════════════════════════════ -->
    <Sheet v-model:open="editSheetOpen">
      <SheetContent side="right" class="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Edit Ad Account</SheetTitle>
          <SheetDescription>
            Update credentials for
            <span class="font-medium text-foreground">{{ editingAccount?.account_name }}</span>
          </SheetDescription>
        </SheetHeader>
        <form v-if="editingAccount" @submit.prevent="handleEditAccount" class="mt-6 space-y-5 px-1">
          <div class="space-y-2">
            <Label class="text-xs">Platform</Label>
            <Input
              :model-value="editingAccount.platform === 'meta' ? 'Meta Ads' : 'Google Ads'"
              disabled
              class="rounded-xl bg-muted/50"
            />
          </div>
          <div class="space-y-2">
            <Label class="text-xs">Account Name</Label>
            <Input v-model="editForm.account_name" class="rounded-xl" />
          </div>
          <div class="space-y-2">
            <Label class="text-xs">Account ID</Label>
            <Input v-model="editForm.account_id" class="rounded-xl" />
          </div>
          <div class="space-y-2">
            <Label class="text-xs">
              {{ editingAccount.platform === 'google' ? 'API Key / Refresh Token' : 'Access Token' }}
            </Label>
            <Input
              v-model="editForm.access_token"
              type="password"
              placeholder="Leave empty to keep current"
              class="rounded-xl"
            />
            <p class="text-[10px] text-muted-foreground">Only fill this if you want to replace the existing token.</p>
          </div>
          <div v-if="editingAccount.platform === 'google'" class="space-y-2">
            <Label class="text-xs">Refresh Token</Label>
            <Input
              v-model="editForm.api_key_or_refresh_token"
              type="password"
              placeholder="Leave empty to keep current"
              class="rounded-xl"
            />
          </div>
          <SheetFooter class="pt-2">
            <SheetClose as-child>
              <Button type="button" variant="outline" class="rounded-xl">Cancel</Button>
            </SheetClose>
            <Button type="submit" :disabled="submittingEdit" class="rounded-xl">
              <Loader2 v-if="submittingEdit" class="mr-2 h-4 w-4 animate-spin" />
              {{ submittingEdit ? 'Saving...' : 'Update Account' }}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>

    <!-- ═══════════════════════════════════════ -->
    <!-- EDIT USER SHEET                         -->
    <!-- ═══════════════════════════════════════ -->
    <Sheet v-model:open="editUserSheetOpen">
      <SheetContent side="right" class="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Edit User</SheetTitle>
          <SheetDescription>
            Update info for
            <span class="font-medium text-foreground">{{ editingUser?.name }}</span>
          </SheetDescription>
        </SheetHeader>
        <form v-if="editingUser" @submit.prevent="handleEditUser" class="mt-6 space-y-5 px-1">
          <div class="space-y-2">
            <Label class="text-xs">Full Name</Label>
            <Input v-model="editUserForm.name" class="rounded-xl" />
          </div>
          <div class="space-y-2">
            <Label class="text-xs">Email</Label>
            <Input v-model="editUserForm.email" type="email" class="rounded-xl" />
          </div>
          <div class="space-y-2">
            <Label class="text-xs">New Password</Label>
            <Input
              v-model="editUserForm.password"
              type="password"
              placeholder="Leave empty to keep current"
              class="rounded-xl"
            />
            <p class="text-[10px] text-muted-foreground">Only fill if you want to change the password.</p>
          </div>
          <SheetFooter class="pt-2">
            <SheetClose as-child>
              <Button type="button" variant="outline" class="rounded-xl">Cancel</Button>
            </SheetClose>
            <Button type="submit" :disabled="submittingEditUser" class="rounded-xl">
              <Loader2 v-if="submittingEditUser" class="mr-2 h-4 w-4 animate-spin" />
              {{ submittingEditUser ? 'Saving...' : 'Update User' }}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  </div>
</template>
