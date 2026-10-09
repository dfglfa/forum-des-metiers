import { useState, use } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { loginConsultant, loginStudent } from '../api/auth'
import { fetchConfig } from '../api/config'
import type { AppConfig } from '../api/config'
import { useAuth } from '../contexts/AuthContext'
import LanguageSwitcher from '../components/LanguageSwitcher'
import styles from './LoginPage.module.css'

type Tab = 'student' | 'consultant'

const configPromise = fetchConfig()

function LoginForm({ config }: { config: AppConfig }) {
  const { t } = useTranslation()
  const [tab, setTab] = useState<Tab>('student')
  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  // Lets a consultant with a real password opt out of the LDAP-styled form even while
  // ldap_consultants is enabled by default. Admin login lives at a separate URL entirely
  // (/admin/login) and never appears here, so this toggle only ever applies to the consultant tab.
  const [forcePasswordLogin, setForcePasswordLogin] = useState(false)

  const { setAuth } = useAuth()
  const navigate = useNavigate()

  const useLdap = tab === 'student' ? true : (config.ldap_consultants && !forcePasswordLogin)
  const identifierLabel = useLdap ? t('login.labelUsername') : t('login.labelEmail')
  const identifierType = useLdap ? 'text' : 'email'
  const identifierAutoComplete = useLdap ? 'username' : 'email'

  function handleTabChange(next: Tab) {
    setTab(next)
    setIdentifier('')
    setError(null)
    setForcePasswordLogin(false)
  }

  async function handleSubmit(e: { preventDefault(): void }) {
    e.preventDefault()
    setError(null)
    setBusy(true)
    try {
      const result = tab === 'student'
        ? await loginStudent(identifier, password)
        : await loginConsultant(identifier, password, useLdap)
      setAuth(result.token, result.user)
      navigate('/dashboard')
    } catch (err: unknown) {
      const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      const msg = anyErr?.response?.data?.errors
        ? Object.values(anyErr.response.data.errors).flat().join(' ')
        : anyErr?.response?.data?.message ?? t('login.errorGeneric')
      setError(msg)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className={styles.page}>
      <div className={styles.card}>
        <div className={styles.topBar}>
          <LanguageSwitcher />
        </div>

        {config.event_logo_url && <img src={config.event_logo_url} alt="" className={styles.logo} />}

        <h1 className={styles.title}>{t('login.title')}</h1>
        <p className={styles.subtitle}>{t('login.subtitle')}</p>

        <div className={styles.tabs}>
          <button
            className={tab === 'student' ? styles.tabActive : styles.tab}
            onClick={() => handleTabChange('student')}
          >
            {t('login.tabStudent')}
          </button>
          <button
            className={tab === 'consultant' ? styles.tabActive : styles.tab}
            onClick={() => handleTabChange('consultant')}
          >
            {t('login.tabConsultant')}
          </button>
        </div>

        <form onSubmit={handleSubmit} className={styles.form}>
          <label className={styles.field}>
            <span>{identifierLabel}</span>
            <input
              type={identifierType}
              value={identifier}
              onChange={(e) => setIdentifier(e.target.value)}
              autoComplete={identifierAutoComplete}
              required
            />
          </label>

          <label className={styles.field}>
            <span>{t('login.labelPassword')}</span>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              required
            />
          </label>

          {error && <p className={styles.error}>{error}</p>}

          <button type="submit" className={styles.submit} disabled={busy}>
            {busy ? t('login.submitting') : t('login.submit')}
          </button>
        </form>

        {tab === 'consultant' && !useLdap && (
          <Link to="/forgot-password" className={styles.switchModeLink}>
            {t('login.forgotPassword')}
          </Link>
        )}

        {tab === 'consultant' && config.ldap_consultants && (
          <button
            type="button"
            className={styles.switchModeLink}
            onClick={() => { setForcePasswordLogin(v => !v); setIdentifier(''); setError(null) }}
          >
            {forcePasswordLogin ? t('login.useLdapInstead') : t('login.useEmailInstead')}
          </button>
        )}

        {tab === 'student' ? (
          <p className={styles.cardFooter}>
            {t('login.studentAccountInfo')}
          </p>
        ) : (
          <p className={styles.cardFooter}>
            {t('login.invitationOnly')}{' '}
            <Link to="/register">{t('login.selfRegisterLink')}</Link>
          </p>
        )}
      </div>
    </div>
  )
}

export default function LoginPage() {
  const config = use(configPromise)
  return <LoginForm config={config} />
}
