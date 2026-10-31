import { Suspense, use, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { DirectoryConsultant } from '../api/consultantDirectory'
import { fetchConsultantDirectory } from '../api/consultantDirectory'
import TopBar from '../components/TopBar'
import styles from './ConsultantDirectoryPage.module.css'

function Silhouette({ gender }: { gender: DirectoryConsultant['gender'] }) {
  return (
    <svg className={styles.photo} viewBox="0 0 120 120" aria-hidden="true">
      <circle cx="60" cy="60" r="60" fill="#e5e7eb" />
      {gender === 'female' && (
        <path d="M60 22c-14 0-22 10-22 24 0 6 1 12 2 18-3 6-8 10-10 20h60c-2-10-7-14-10-20 1-6 2-12 2-18 0-14-8-24-22-24z" fill="#9ca3af" />
      )}
      <circle cx="60" cy="48" r="17" fill="#9ca3af" />
      <path d="M26 112c0-20 14-32 34-32s34 12 34 32a60 60 0 0 1-68 0z" fill="#9ca3af" />
    </svg>
  )
}

function DirectoryList({ promise }: { promise: ReturnType<typeof fetchConsultantDirectory> }) {
  const { t } = useTranslation()
  const consultants = use(promise)

  if (consultants.length === 0) return <p className={styles.empty}>{t('directory.empty')}</p>

  // The list arrives sorted by topic, so consecutive entries with the same topic form a group.
  const groups: { tag: string | null; items: DirectoryConsultant[] }[] = []
  for (const c of consultants) {
    const last = groups[groups.length - 1]
    if (last && last.tag === c.tag) last.items.push(c)
    else groups.push({ tag: c.tag, items: [c] })
  }

  return (
    <>
      {groups.map(group => (
        <section key={group.tag ?? ''}>
          <h2 className={styles.groupTitle}>{group.tag ?? t('directory.noTopic')}</h2>
          <div className={styles.grid}>
            {group.items.map(c => (
              <div key={c.id} className={styles.card}>
                {c.profile_picture_url
                  ? <img className={styles.photo} src={c.profile_picture_url} alt="" />
                  : <Silhouette gender={c.gender} />}
                <span className={styles.name}>{c.name}</span>
                {c.graduation_year && (
                  <span className={styles.year}>{t('directory.graduationYear', { year: c.graduation_year })}</span>
                )}
              </div>
            ))}
          </div>
        </section>
      ))}
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
