import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { sendSpeakerMail } from '../../api/invite'
import ConfirmDialog from '../../components/ConfirmDialog'
import TopBar from '../../components/TopBar'
import styles from './InviteSpeakerPage.module.css'
import listStyles from './AdminListPage.module.css'

export default function SpeakerMailPage() {
  const { t } = useTranslation()

  const [subjectDe, setSubjectDe] = useState('')
  const [subjectFr, setSubjectFr] = useState('')
  const [bodyDe, setBodyDe] = useState('')
  const [bodyFr, setBodyFr] = useState('')
  const [confirming, setConfirming] = useState(false)
  const [busy, setBusy] = useState(false)
  const [sentCount, setSentCount] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function handleSend() {
    setBusy(true)
    setError(null)
    setSentCount(null)
    try {
      const res = await sendSpeakerMail({ subject_de: subjectDe, subject_fr: subjectFr, body_de: bodyDe, body_fr: bodyFr })
      setSentCount(res.sent_count)
      setSubjectDe('')
      setSubjectFr('')
      setBodyDe('')
      setBodyFr('')
    } catch (err: unknown) {
      const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      setError(
        anyErr?.response?.data?.errors
          ? Object.values(anyErr.response.data.errors).flat().join(' ')
          : anyErr?.response?.data?.message ?? t('admin.speakerMail.errorGeneric'),
      )
    } finally {
      setBusy(false)
      setConfirming(false)
    }
  }

  return (
    <div className={listStyles.page}>
      <TopBar backTo="/admin/consultants" backLabel={t('admin.consultantDetail.backToList')} />

      <main className={listStyles.main}>
        <h1 className={listStyles.title}>{t('admin.speakerMail.title')}</h1>

        <div className={styles.formCard}>
          <form
            onSubmit={e => {
              e.preventDefault()
              setConfirming(true)
            }}
            className={styles.form}
          >
            <label className={styles.field}>
              <span>{t('admin.speakerMail.fieldSubjectDe')}</span>
              <input type="text" value={subjectDe} onChange={e => setSubjectDe(e.target.value)} maxLength={200} required />
            </label>
            <label className={styles.field}>
              <span>{t('admin.speakerMail.fieldBodyDe')}</span>
              <textarea value={bodyDe} onChange={e => setBodyDe(e.target.value)} rows={8} required className={styles.textarea} />
            </label>

            <label className={styles.field}>
              <span>{t('admin.speakerMail.fieldSubjectFr')}</span>
              <input type="text" value={subjectFr} onChange={e => setSubjectFr(e.target.value)} maxLength={200} required />
            </label>
            <label className={styles.field}>
              <span>{t('admin.speakerMail.fieldBodyFr')}</span>
              <textarea value={bodyFr} onChange={e => setBodyFr(e.target.value)} rows={8} required className={styles.textarea} />
              <span className={styles.hint}>{t('admin.speakerMail.hint')}</span>
            </label>

            {error && <p className={styles.error}>{error}</p>}
            {sentCount !== null && <p className={styles.success}>{t('admin.speakerMail.success', { count: sentCount })}</p>}

            <button type="submit" className={styles.submit} disabled={busy}>
              {busy ? t('admin.speakerMail.submitting') : t('admin.speakerMail.submit')}
            </button>
          </form>
        </div>
      </main>

      {confirming && (
        <ConfirmDialog
          title={t('admin.speakerMail.confirmTitle')}
          description={t('admin.speakerMail.confirmDesc')}
          cancelLabel={t('admin.speakerMail.cancel')}
          confirmLabel={t('admin.speakerMail.confirmButton')}
          busy={busy}
          onCancel={() => setConfirming(false)}
          onConfirm={handleSend}
        />
      )}
    </div>
  )
}
