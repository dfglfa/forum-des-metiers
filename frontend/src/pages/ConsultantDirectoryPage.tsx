import { Suspense, use, useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchConsultantDirectory } from '../api/consultantDirectory'
import type { DirectoryConsultant } from '../api/consultantDirectory'
import TopBar from '../components/TopBar'
import styles from './ConsultantDirectoryPage.module.css'

function Initials({ name }: { name: string }) {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  const letters = parts.length > 1
    ? parts[0][0] + parts[parts.length - 1][0]
    : (parts[0] ?? '').slice(0, 2)
  return <div className={`${styles.photo} ${styles.initials}`} aria-hidden="true">{letters.toUpperCase()}</div>
}

function DetailDialog({ consultant: c, onClose }: { consultant: DirectoryConsultant; onClose: () => void }) {
  const { t } = useTranslation()

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('keydown', onKey)
    const prevOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = prevOverflow
    }
  }, [onClose])

  const sections = [
    { label: t('profile.fieldCareerPath'), text: c.career_path },
    { label: t('profile.fieldCurrentSituation'), text: c.current_situation },
    { label: t('profile.fieldWhyThisCareer'), text: c.why_this_career },
  ].filter(s => s.text)

  return (
    <div className={styles.backdrop} onClick={onClose}>
      <div
        className={styles.dialog}
        role="dialog"
        aria-modal="true"
        aria-labelledby="consultant-dialog-title"
        onClick={e => e.stopPropagation()}
      >
        <button className={styles.closeBtn} onClick={onClose} aria-label={t('directory.close')}>×</button>
        <div className={styles.dialogHeader}>
          {c.profile_picture_url
            ? <img className={styles.photo} src={c.profile_picture_url} alt="" />
            : <Initials name={c.name} />}
          <h2 id="consultant-dialog-title" className={styles.dialogName}>{c.name}</h2>
          {c.graduation_year && (
            <span className={styles.year}>{t('directory.graduationYear', { year: c.graduation_year })}</span>
          )}
          {c.serie && <span className={styles.year}>{t('directory.serie', { serie: c.serie })}</span>}
        </div>
        {sections.map(s => (
          <section key={s.label} className={styles.section}>
            <h3 className={styles.sectionTitle}>{s.label}</h3>
            <p className={styles.sectionText}>{s.text}</p>
          </section>
        ))}
      </div>
    </div>
  )
}

function DirectoryList({ promise }: { promise: ReturnType<typeof fetchConsultantDirectory> }) {
  const { t } = useTranslation()
  const consultants = use(promise)
  const [selected, setSelected] = useState<DirectoryConsultant | null>(null)

  if (consultants.length === 0) return <p className={styles.empty}>{t('directory.empty')}</p>

  return (
    <>
    <div className={styles.grid}>
      {consultants.map(c => (
        <button type="button" key={c.id} className={styles.card} onClick={() => setSelected(c)}>
          {c.profile_picture_url
            ? <img className={styles.photo} src={c.profile_picture_url} alt="" />
            : <Initials name={c.name} />}
          <span className={styles.name}>{c.name}</span>
          {c.graduation_year && (
            <span className={styles.year}>{t('directory.graduationYear', { year: c.graduation_year })}</span>
          )}
        </button>
      ))}
    </div>
    {selected && <DetailDialog consultant={selected} onClose={() => setSelected(null)} />}
    </>
  )
}

export default function ConsultantDirectoryPage() {
  const { t } = useTranslation()
  const [promise] = useState(fetchConsultantDirectory)

  return (
    <div className={styles.page}>
      <TopBar backTo="/dashboard" backLabel={t('directory.back')} />
      <main className={styles.main}>
        <h1 className={styles.title}>{t('directory.title')}</h1>
        <Suspense fallback={<div>…</div>}>
          <DirectoryList promise={promise} />
        </Suspense>
      </main>
    </div>
  )
}
