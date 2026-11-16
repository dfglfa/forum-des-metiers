import { Suspense, use, useImperativeHandle, useState } from 'react'
import type { Ref } from 'react'
import { useTranslation } from 'react-i18next'
import { isAxiosError } from 'axios'
import { fetchConsultantProfile, updateConsultantProfile } from '../api/profile'
import type { ConsultantProfileResponse, ConsultantProfileData } from '../api/profile'
import { fetchSeries } from '../api/series'
import type { SeriesOption } from '../api/series'
import { fetchConfig } from '../api/config'
import type { GraduationYearRange } from '../api/config'
import styles from './ConsultantProfilePage.module.css'
import TopBar from '../components/TopBar'
import type { FormHandle } from '../utils/formHandle'

const MAX_PHOTO_BYTES = 2 * 1024 * 1024
const PHONE_RE = /^[0-9+\-().\/ ]+$/
const URL_RE = /^https?:\/\/\S+$/i

type FieldErrors = Partial<Record<string, string>>

/** Mirrors the backend: a missing scheme is added before validating. */
function normalizeUrl(url: string): string {
  const u = url.trim()
  return u && !/^[a-z][a-z0-9+.-]*:\/\//i.test(u) ? `https://${u.replace(/^\/+/, '')}` : u
}

export function ProfileForm({
  initial,
  seriesOptions,
  graduationYearRange,
  hideFooter = false,
  formRef,
}: {
  initial: ConsultantProfileResponse
  seriesOptions: SeriesOption[]
  graduationYearRange: GraduationYearRange
  hideFooter?: boolean
  formRef?: Ref<FormHandle>
}) {
  const { t } = useTranslation()
  const p = initial.profile

  const [firstName, setFirstName] = useState(p?.first_name ?? '')
  const [lastName, setLastName] = useState(p?.last_name ?? '')
  const [phone, setPhone] = useState(p?.phone ?? '')
  const [graduationYear, setGraduationYear] = useState(p?.graduation_year?.toString() ?? '')
  const [serie, setSerie] = useState(p?.serie ?? '')
  const [language, setLanguage] = useState(p?.language ?? 'de')
  const [linkedinUrl, setLinkedinUrl] = useState(p?.linkedin_url ?? '')
  const [careerPath, setCareerPath] = useState(p?.career_path ?? '')
  const [currentSituation, setCurrentSituation] = useState(p?.current_situation ?? '')
  const [whyThisCareer, setWhyThisCareer] = useState(p?.why_this_career ?? '')
  const [consentPoster, setConsentPoster] = useState(p?.consent_poster ?? false)
  const [consentAlumniData, setConsentAlumniData] = useState(p?.consent_alumni_data ?? false)
  const [photo, setPhoto] = useState<File | null>(null)
  const [photoPreview, setPhotoPreview] = useState<string | null>(p?.profile_picture_url ?? null)

  const [busy, setBusy] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})

  function clearFieldError(key: string) {
    setFieldErrors(prev => (prev[key] ? { ...prev, [key]: undefined } : prev))
  }

  function validatePhoto(file: File): string | null {
    if (!file.type.startsWith('image/')) return t('profile.errorPhotoType')
    if (file.size > MAX_PHOTO_BYTES) {
      return t('profile.errorPhotoSize', { size: (file.size / 1024 / 1024).toFixed(1) })
    }
    return null
  }

  function validate(): FieldErrors {
    const errors: FieldErrors = {}
    if (firstName.length > 100) errors.first_name = t('profile.errorTooLong', { max: 100 })
    if (lastName.length > 100) errors.last_name = t('profile.errorTooLong', { max: 100 })
    const phoneTrimmed = phone.trim()
    if (phoneTrimmed && (phoneTrimmed.length > 30 || !PHONE_RE.test(phoneTrimmed))) {
      errors.phone = t('profile.errorPhone')
    }
    if (graduationYear) {
      const year = Number(graduationYear)
      if (!Number.isInteger(year) || year < graduationYearRange.min || year > graduationYearRange.max) {
        errors.graduation_year = t('profile.errorGraduationYear', {
          min: graduationYearRange.min,
          max: graduationYearRange.max,
        })
      }
    }
    const url = normalizeUrl(linkedinUrl)
    if (url && (!URL_RE.test(url) || url.length > 255)) errors.linkedin_url = t('profile.errorLinkedin')
    if (photo) {
      const photoError = validatePhoto(photo)
      if (photoError) errors.profile_picture = photoError
    }
    return errors
  }

  /** Translates a failed save response into per-field messages. */
  function serverFieldErrors(err: unknown): FieldErrors | null {
    if (!isAxiosError(err) || !err.response) return null
    if (err.response.status === 413) return { profile_picture: t('profile.errorPhotoSize', { size: '?' }) }
    if (err.response.status !== 422) return null
    const raw = (err.response.data as { errors?: Record<string, string[]> })?.errors
    if (!raw) return null
    const known: Record<string, string> = {
      phone: t('profile.errorPhone'),
      linkedin_url: t('profile.errorLinkedin'),
      graduation_year: t('profile.errorGraduationYear', { min: graduationYearRange.min, max: graduationYearRange.max }),
      profile_picture: t('profile.errorPhotoType'),
    }
    const result: FieldErrors = {}
    for (const [key, messages] of Object.entries(raw)) {
      result[key] = key === 'profile_picture' && messages.some(m => /\b2048\b|kilobytes|Kilobyte/i.test(m))
        ? t('profile.errorPhotoSize', { size: photo ? (photo.size / 1024 / 1024).toFixed(1) : '?' })
        : known[key] ?? messages[0]
    }
    return result
  }

  function handlePhotoChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null
    clearFieldError('profile_picture')
    if (file) {
      const photoError = validatePhoto(file)
      if (photoError) {
        // Reject right away so the user can pick another file.
        e.target.value = ''
        setPhoto(null)
        setFieldErrors(prev => ({ ...prev, profile_picture: photoError }))
        return
      }
      setPhotoPreview(URL.createObjectURL(file))
    }
    setPhoto(file)
  }

  async function save(): Promise<string | null> {
    setBusy(true)
    setSuccess(false)
    setError(null)
    const errors = validate()
    if (Object.values(errors).some(Boolean)) {
      setFieldErrors(errors)
      setBusy(false)
      const message = t('profile.errorValidation')
      setError(message)
      return message
    }
    setFieldErrors({})
    try {
      const fields: Partial<Omit<ConsultantProfileData, 'id' | 'profile_picture_path' | 'profile_picture_url'>> = {
        first_name: firstName || null,
        last_name: lastName || null,
        phone: phone || null,
        graduation_year: graduationYear ? Number(graduationYear) : null,
        serie: serie || null,
        language: language || null,
        linkedin_url: linkedinUrl || null,
        career_path: careerPath || null,
        current_situation: currentSituation || null,
        why_this_career: whyThisCareer || null,
        consent_poster: consentPoster,
        consent_alumni_data: consentAlumniData,
      }
      await updateConsultantProfile(fields, photo ?? undefined)
      setSuccess(true)
      setPhoto(null)
      return null
    } catch (err) {
      const serverErrors = serverFieldErrors(err)
      if (serverErrors) setFieldErrors(serverErrors)
      const message = serverErrors ? t('profile.errorValidation') : t('profile.errorSave')
      setError(message)
      return message
    } finally {
      setBusy(false)
    }
  }

  useImperativeHandle(formRef, () => ({ save }))

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    void save()
  }

  return (
    <form onSubmit={handleSubmit} noValidate className={styles.card}>
      {/* Photo */}
      <div className={styles.section}>
        <div className={styles.photoSection}>
          {photoPreview
            ? <img src={photoPreview} alt="" className={styles.avatar} />
            : <div className={styles.avatarPlaceholder}>👤</div>
          }
          <div className={styles.photoInput}>
            <label htmlFor="photo-input">{t('profile.fieldProfilePicture')}</label>
            <input
              id="photo-input"
              type="file"
              accept="image/*"
              onChange={handlePhotoChange}
              aria-invalid={!!fieldErrors.profile_picture}
            />
            <FieldError message={fieldErrors.profile_picture} />
          </div>
        </div>
      </div>

      {/* Personal info */}
      <div className={styles.section}>
        <div className={styles.row}>
          <div className={styles.field}>
            <label htmlFor="last-name">{t('profile.fieldLastName')}</label>
            <input
              id="last-name"
              type="text"
              value={lastName}
              onChange={e => { setLastName(e.target.value); clearFieldError('last_name') }}
              aria-invalid={!!fieldErrors.last_name}
            />
            <FieldError message={fieldErrors.last_name} />
          </div>
          <div className={styles.field}>
            <label htmlFor="first-name">{t('profile.fieldFirstName')}</label>
            <input
              id="first-name"
              type="text"
              value={firstName}
              onChange={e => { setFirstName(e.target.value); clearFieldError('first_name') }}
              aria-invalid={!!fieldErrors.first_name}
            />
            <FieldError message={fieldErrors.first_name} />
          </div>
        </div>
        <div className={styles.row}>
          <div className={styles.field}>
            <label htmlFor="phone">{t('profile.fieldPhone')}</label>
            <input
              id="phone"
              type="tel"
              value={phone}
              onChange={e => { setPhone(e.target.value); clearFieldError('phone') }}
              aria-invalid={!!fieldErrors.phone}
            />
            <FieldError message={fieldErrors.phone} />
          </div>
          <div className={styles.field}>
            <label htmlFor="graduation-year">{t('profile.fieldGraduationYear')}</label>
            <input
              id="graduation-year"
              type="number"
              min={graduationYearRange.min}
              max={graduationYearRange.max}
              value={graduationYear}
              onChange={e => { setGraduationYear(e.target.value); clearFieldError('graduation_year') }}
              aria-invalid={!!fieldErrors.graduation_year}
            />
            <FieldError message={fieldErrors.graduation_year} />
          </div>
        </div>
        <div className={styles.row}>
          <div className={styles.field}>
            <label htmlFor="serie">{t('profile.fieldSerie')}</label>
            <select id="serie" value={serie} onChange={e => setSerie(e.target.value)}>
              <option value="">—</option>
              {seriesOptions.map(s => <option key={s.id} value={s.name}>{s.name}</option>)}
            </select>
          </div>
          <div className={styles.field}>
            <label htmlFor="linkedin">{t('profile.fieldLinkedin')}</label>
            <input
              id="linkedin"
              type="text"
              inputMode="url"
              placeholder="https://www.linkedin.com/in/…"
              value={linkedinUrl}
              onChange={e => { setLinkedinUrl(e.target.value); clearFieldError('linkedin_url') }}
              aria-invalid={!!fieldErrors.linkedin_url}
            />
            <FieldError message={fieldErrors.linkedin_url} />
          </div>
        </div>
        <div className={styles.field}>
          <label htmlFor="language">{t('profile.fieldLanguage')}</label>
          <select id="language" value={language} onChange={e => setLanguage(e.target.value)}>
            <option value="de">{t('lang.de')}</option>
            <option value="fr">{t('lang.fr')}</option>
            <option value="de_fr">{t('lang.de_fr')}</option>
          </select>
        </div>
      </div>

      {/* Career */}
      <div className={styles.section}>
        <div className={styles.field}>
          <label htmlFor="career-path">{t('profile.fieldCareerPath')}</label>
          <textarea
            id="career-path"
            value={careerPath}
            onChange={e => setCareerPath(e.target.value)}
          />
        </div>
        <div className={styles.field}>
          <label htmlFor="current-situation">{t('profile.fieldCurrentSituation')}</label>
          <textarea
            id="current-situation"
            value={currentSituation}
            onChange={e => setCurrentSituation(e.target.value)}
          />
        </div>
        <div className={styles.field}>
          <label htmlFor="why-this-career">{t('profile.fieldWhyThisCareer')}</label>
          <textarea
            id="why-this-career"
            value={whyThisCareer}
            onChange={e => setWhyThisCareer(e.target.value)}
          />
        </div>
      </div>

      {/* Consent */}
      <div className={styles.section}>
        <label className={styles.consentRow}>
          <input
            type="checkbox"
            checked={consentPoster}
            onChange={e => setConsentPoster(e.target.checked)}
          />
          <span>{t('profile.consentPoster')}</span>
        </label>
        <label className={styles.consentRow}>
          <input
            type="checkbox"
            checked={consentAlumniData}
            onChange={e => setConsentAlumniData(e.target.checked)}
          />
          <span>{t('profile.consentAlumniData')}</span>
        </label>
      </div>

      {!hideFooter && (
        <div className={styles.footer}>
          <button type="submit" className={styles.saveBtn} disabled={busy}>
            {busy ? t('profile.saving') : t('profile.save')}
          </button>
          {success && <span className={styles.successMsg}>{t('profile.saved')}</span>}
          {error && <span className={styles.errorMsg}>{error}</span>}
        </div>
      )}
    </form>
  )
}

function FieldError({ message }: { message?: string }) {
  return message ? <span className={styles.fieldError} role="alert">{message}</span> : null
}

function ProfilePageContent({
  profilePromise,
  seriesPromise,
  configPromise,
}: {
  profilePromise: Promise<ConsultantProfileResponse>
  seriesPromise: Promise<SeriesOption[]>
  configPromise: ReturnType<typeof fetchConfig>
}) {
  const initial = use(profilePromise)
  const seriesOptions = use(seriesPromise)
  const config = use(configPromise)
  const { t } = useTranslation()
  return (
    <div className={styles.page}>
      <TopBar backTo="/dashboard" backLabel={t('admin.backToDashboard')} />
      <main className={styles.main}>
        <h1 className={styles.title}>{t('profile.title')}</h1>
        <ProfileForm initial={initial} seriesOptions={seriesOptions} graduationYearRange={config.graduation_year_range} />
      </main>
    </div>
  )
}

export default function ConsultantProfilePage() {
  const [profilePromise] = useState(fetchConsultantProfile)
  const [seriesPromise] = useState(fetchSeries)
  const [configPromise] = useState(fetchConfig)
  return (
    <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
      <ProfilePageContent profilePromise={profilePromise} seriesPromise={seriesPromise} configPromise={configPromise} />
    </Suspense>
  )
}
