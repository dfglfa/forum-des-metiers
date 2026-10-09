import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { register, resendVerification } from '../api/auth'
import { LANGUAGE_OPTIONS, SALUTATION_OPTIONS } from '../api/invite'
import LanguageSwitcher from '../components/LanguageSwitcher'
import styles from './LoginPage.module.css'

export default function RegisterPage() {
  const { t } = useTranslation()

  // Students never self-register (they log in via LDAP username, provisioned by the admin's CSV
  // import) — this form is consultant-only. It captures the same fields the admin's invitation
  // flow does (salutation, first/last name, language) so a self-registered account looks
  // identical to an invited-and-activated one.
  const [salutation, setSalutation] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [email, setEmail] = useState('')
  const [language, setLanguage] = useState<(typeof LANGUAGE_OPTIONS)[number]>('de')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [submitted, setSubmitted] = useState(false)
  const [resendBusy, setResendBusy] = useState(false)
  const [resendDone, setResendDone] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    setBusy(true)
    try {
      await register({
        salutation,
        first_name: firstName,
        last_name: lastName,
        email,
        password,
        password_confirmation: passwordConfirmation,
        language,
      })
      setSubmitted(true)
    } catch (err: unknown) {
      const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      const msg = anyErr?.response?.data?.errors
        ? Object.values(anyErr.response.data.errors).flat().join(' ')
        : anyErr?.response?.data?.message ?? t('register.errorGeneric')
      setError(msg)
    } finally {
      setBusy(false)
    }
  }

  async function handleResend() {
    setResendBusy(true)
    setResendDone(false)
    try {
      await resendVerification(email)
      setResendDone(true)
    } finally {
      setResendBusy(false)
    }
  }

  if (submitted) {
    return (
      <div className={styles.page}>
        <div className={styles.card}>
          <div className={styles.topBar}>
            <LanguageSwitcher />
          </div>
          <h1 className={styles.title}>{t('register.checkEmail')}</h1>
          <p style={{ textAlign: 'center', color: 'var(--color-text-secondary, #555)', marginBottom: '1.5rem' }}>
            {t('register.checkEmailDesc', { email })}
          </p>
          {resendDone
            ? <p style={{ textAlign: 'center', color: 'var(--color-success, #2d7d46)' }}>{t('register.resent')}</p>
            : (
              <button
                type="button"
                className={styles.submit}
                onClick={handleResend}
                disabled={resendBusy}
                style={{ marginBottom: '1rem' }}
              >
                {resendBusy ? t('register.resending') : t('register.resendEmail')}
              </button>
            )
          }
          <p className={styles.cardFooter}>
            {t('register.haveAccount')}{' '}
            <Link to="/login">{t('register.signIn')}</Link>
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className={styles.page}>
      <div className={styles.card}>
        <div className={styles.topBar}>
          <LanguageSwitcher />
        </div>

        <h1 className={styles.title}>{t('register.title')}</h1>
        <p className={styles.subtitle}>{t('register.subtitle')}</p>

        <form onSubmit={handleSubmit} className={styles.form}>
          <label className={styles.field}>
            <span>{t('register.labelSalutation')}</span>
            <select
              value={salutation}
              onChange={e => setSalutation(e.target.value)}
              required
              autoComplete="honorific-prefix"
            >
              <option value="" disabled>
                {t('register.labelSalutationPlaceholder')}
              </option>
              {SALUTATION_OPTIONS.map(option => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </label>

          <label className={styles.field}>
            <span>{t('register.labelFirstName')}</span>
            <input
              type="text"
              value={firstName}
              onChange={e => setFirstName(e.target.value)}
              autoComplete="given-name"
              required
            />
          </label>

          <label className={styles.field}>
            <span>{t('register.labelLastName')}</span>
            <input
              type="text"
              value={lastName}
              onChange={e => setLastName(e.target.value)}
              autoComplete="family-name"
              required
            />
          </label>

          <label className={styles.field}>
            <span>{t('login.labelEmail')}</span>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              autoComplete="email"
              required
            />
          </label>

          <label className={styles.field}>
            <span>{t('register.labelLanguage')}</span>
            <select
              value={language}
              onChange={e => setLanguage(e.target.value as (typeof LANGUAGE_OPTIONS)[number])}
              required
            >
              {LANGUAGE_OPTIONS.map(option => (
                <option key={option} value={option}>
                  {t(`lang.${option}`)}
                </option>
              ))}
            </select>
          </label>

          <label className={styles.field}>
            <span>{t('login.labelPassword')}</span>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              autoComplete="new-password"
              minLength={8}
              required
            />
          </label>

          <label className={styles.field}>
            <span>{t('register.labelPasswordConfirm')}</span>
            <input
              type="password"
              value={passwordConfirmation}
              onChange={e => setPasswordConfirmation(e.target.value)}
              autoComplete="new-password"
              minLength={8}
              required
            />
          </label>

          {error && <p className={styles.error}>{error}</p>}

          <button type="submit" className={styles.submit} disabled={busy}>
            {busy ? t('register.submitting') : t('register.submit')}
          </button>
        </form>

        <p className={styles.cardFooter}>
          {t('register.haveAccount')}{' '}
          <Link to="/login">{t('register.signIn')}</Link>
        </p>
      </div>
    </div>
  )
}
