import { createContext, useContext, useState, use, Suspense } from 'react'
import type { ReactNode } from 'react'
import { getMe, logout as apiLogout } from '../api/auth'
import type { User } from '../api/auth'
import i18n from '../i18n'

// Students can have a language preset by the CSV import; it overrides the browser's choice.
function applyUserLanguage(user: User) {
  if (user.language) void i18n.changeLanguage(user.language)
}

interface AuthContextValue {
  user: User | null
  setAuth: (token: string, user: User) => void
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

async function initUserPromise(): Promise<User | null> {
  const token = localStorage.getItem('token')
  const role = localStorage.getItem('role') as User['role'] | null
  if (!token || !role) return null
  try {
    const me = await getMe(role)
    applyUserLanguage(me)
    return me
  } catch {
    localStorage.clear()
    return null
  }
}

function AuthConsumer({ userPromise, setUserPromise, children }: {
  userPromise: Promise<User | null>
  setUserPromise: (p: Promise<User | null>) => void
  children: ReactNode
}) {
  const user = use(userPromise)

  function setAuth(token: string, newUser: User) {
    localStorage.setItem('token', token)
    localStorage.setItem('role', newUser.role)
    applyUserLanguage(newUser)
    setUserPromise(Promise.resolve(newUser))
  }

  async function logout() {
    const role = user?.role
    try { if (role) await apiLogout(role) } catch {}
    localStorage.clear()
    setUserPromise(Promise.resolve(null))
  }

  return (
    <AuthContext.Provider value={{ user, setAuth, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [userPromise, setUserPromise] = useState(initUserPromise)

  return (
    <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>Loading…</div>}>
      <AuthConsumer userPromise={userPromise} setUserPromise={setUserPromise}>
        {children}
      </AuthConsumer>
    </Suspense>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider')
  return ctx
}
