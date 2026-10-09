import { Suspense, use, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchConsultantProfile, updateConsultantProfile } from '../api/profile'
import type { ConsultantProfileResponse, ConsultantProfileData } from '../api/profile'
import { fetchSeries } from '../api/series'
import type { SeriesOption } from '../api/series'
import { fetchConfig } from '../api/config'
import type { GraduationYearRange } from '../api/config'
import styles from './ConsultantProfilePage.module.css'
import TopBar from '../components/TopBar'

export function ProfileForm({
  initial,
  seriesOptions,
  graduationYearRange,
}: {
  initial: ConsultantProfileResponse
  seriesOptions: SeriesOption[]
  graduationYearRange: GraduationYearRange
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

  function handlePhotoChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null
    setPhoto(file)
    if (file) setPhotoPreview(URL.createObjectURL(file))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    setSuccess(false)
    setError(null)
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
    } catch {
      setError(t('profile.errorSave'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className={styles.card}>
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
            />
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
              onChange={e => setLastName(e.target.value)}
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="first-name">{t('profile.fieldFirstName')}</label>
            <input
              id="first-name"
              type="text"
              value={firstName}
              onChange={e => setFirstName(e.target.value)}
            />
          </div>
        </div>
        <div className={styles.row}>
          <div className={styles.field}>
            <label htmlFor="phone">{t('profile.fieldPhone')}</label>
            <input
              id="phone"
              type="tel"
              value={phone}
              onChange={e => setPhone(e.target.value)}
            />
          </div>
          <div className={styles.field}>
            <label htmlFor="graduation-year">{t('profile.fieldGraduationYear')}</label>
            <input
              id="graduation-year"
              type="number"
              min={graduationYearRange.min}
              max={graduationYearRange.max}
              value={graduationYear}
              onChange={e => setGraduationYear(e.target.value)}
            />
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
              type="url"
              value={linkedinUrl}
              onChange={e => setLinkedinUrl(e.target.value)}
              placeholder="https://linkedin.com/in/…"
            />
          </div>
        </div>
        <div className={styles.field}>
          <label htmlFor="language">{t('profile.fieldLanguage')}</label>
          <select id="language" value={language} onChange={e => setLanguage(e.target.value)}>
            <option value="de">{t('lang.de')}</option>
            <option value="fr">{t('lang.fr')}</option>
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

      <div className={styles.footer}>
        <button type="submit" className={styles.saveBtn} disabled={busy}>
          {busy ? t('profile.saving') : t('profile.save')}
        </button>
        {success && <span className={styles.successMsg}>{t('profile.saved')}</span>}
        {error && <span className={styles.errorMsg}>{error}</span>}
      </div>
    </form>
  )
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
