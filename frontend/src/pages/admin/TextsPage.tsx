import { Suspense, use, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchConfig, setEventTexts } from '../../api/config'
import type { AppConfig } from '../../api/config'
import listStyles from './AdminListPage.module.css'
import formStyles from './InviteSpeakerPage.module.css'
import dashboardStyles from '../DashboardPage.module.css'
import TopBar from '../../components/TopBar'

function EventDescriptionForm({ config }: { config: AppConfig }) {
  const { t } = useTranslation()

  const [de, setDe] = useState(config.event_description.de)
  const [fr, setFr] = useState(config.event_description.fr)
  const [busy, setBusy] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    setSuccess(false)
    setError(null)
    try {
      await setEventTexts({ description_de: de, description_fr: fr })
      setSuccess(true)
    } catch (err: unknown) {
      const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      const msg = anyErr?.response?.data?.errors
        ? Object.values(anyErr.response.data.errors).flat().join(' ')
        : anyErr?.response?.data?.message ?? t('admin.eventTexts.errorGeneric')
      setError(msg)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className={formStyles.formCard}>
      <form onSubmit={handleSubmit} className={formStyles.form}>
        <label className={formStyles.field}>
          <span>{t('admin.eventTexts.fieldDe')}</span>
          <textarea
            value={de}
            onChange={e => setDe(e.target.value)}
            rows={6}
            maxLength={5000}
            className={formStyles.textarea}
          />
        </label>
        <label className={formStyles.field}>
          <span>{t('admin.eventTexts.fieldFr')}</span>
          <textarea
            value={fr}
            onChange={e => setFr(e.target.value)}
            rows={6}
            maxLength={5000}
            className={formStyles.textarea}
          />
        </label>

        {error && <p className={formStyles.error}>{error}</p>}
        {success && <p className={formStyles.success}>{t('admin.eventTexts.success')}</p>}

        <button type="submit" className={formStyles.submit} disabled={busy}>
          {busy ? t('admin.eventTexts.submitting') : t('admin.eventTexts.submit')}
        </button>
      </form>
    </div>
  )
}

function TextsPageContent({ configPromise }: { configPromise: Promise<AppConfig> }) {
  const config = use(configPromise)
  const { t } = useTranslation()

  return (
    <>
      <span className={dashboardStyles.phaseLabel}>{t('admin.eventTexts.descriptionLabel')}</span>
      <p className={formStyles.hint}>{t('admin.eventTexts.htmlHint')}</p>
      <EventDescriptionForm config={config} />
    </>
  )
}

export default function TextsPage() {
  const { t } = useTranslation()
  const [configPromise] = useState(() => fetchConfig())

  return (
    <div className={listStyles.page}>
      <TopBar backTo="/dashboard" backLabel={t('admin.backToDashboard')} />
      <main className={listStyles.main}>
        <h1 className={listStyles.title}>{t('admin.textsSection')}</h1>
        <Suspense fallback={<p className={listStyles.empty}>…</p>}>
          <TextsPageContent configPromise={configPromise} />
        </Suspense>
      </main>
    </div>
  )
}
