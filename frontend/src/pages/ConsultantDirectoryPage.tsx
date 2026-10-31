import { Suspense, use, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchConsultantDirectory } from '../api/consultantDirectory'
import TopBar from '../components/TopBar'
import styles from './ConsultantDirectoryPage.module.css'

function Initials({ name }: { name: string }) {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  const letters = parts.length > 1
    ? parts[0][0] + parts[parts.length - 1][0]
    : (parts[0] ?? '').slice(0, 2)
  return <div className={`${styles.photo} ${styles.initials}`} aria-hidden="true">{letters.toUpperCase()}</div>
}

function DirectoryList({ promise }: { promise: ReturnType<typeof fetchConsultantDirectory> }) {
  const { t } = useTranslation()
  const consultants = use(promise)

  if (consultants.length === 0) return <p className={styles.empty}>{t('directory.empty')}</p>

  return (
    <div className={styles.grid}>
      {consultants.map(c => (
        <div key={c.id} className={styles.card}>
          {c.profile_picture_url
            ? <img className={styles.photo} src={c.profile_picture_url} alt="" />
            : <Initials name={c.name} />}
          <span className={styles.name}>{c.name}</span>
          {c.graduation_year && (
            <span className={styles.year}>{t('directory.graduationYear', { year: c.graduation_year })}</span>
          )}
        </div>
      ))}
    </div>
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
