import { Suspense, use, useState } from 'react'
import { Trans, useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import mainImage from '../../public/fdm.png'
import type { AppConfig } from '../api/config'
import { fetchConfig } from '../api/config'
import { fetchConsultantProfile } from '../api/profile'
import { fetchSeries } from '../api/series'
import { buildSlotGroups, fetchConsultantSession } from '../api/session'
import { fetchSlotOptions } from '../api/slotOptions'
import type { StudentSelectionData } from '../api/studentSelection'
import { fetchStudentSelection } from '../api/studentSelection'
import TopBar from '../components/TopBar'
import { useAuth } from '../contexts/AuthContext'
import { formatPhaseDate } from '../utils/formatPhaseDate'
import { getFirstName } from '../utils/getFirstName'
import { getTimeOfDay } from '../utils/timeOfDay'
import { ProfileForm } from './ConsultantProfilePage'
import { SessionForm, SessionReadOnly } from './ConsultantSessionPage'
import styles from './DashboardPage.module.css'

const configPromise = fetchConfig()

export default function DashboardPage() {
  const { user } = useAuth()

  if (!user) return null

  return (
    <div className={styles.page}>
      <TopBar />

      <main className={styles.main}>
        <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
          {user.role === 'student' && <StudentDashboard name={user.name} />}
          {user.role === 'consultant' && <ConsultantDashboard name={user.name} />}
          {user.role === 'admin' && <AdminDashboard name={user.name} />}
        </Suspense>
      </main>
    </div>
  )
}

const STUDENT_GREETING_KEYS = {
  morning: 'dashboard.greetingStudentMorning',
  day: 'dashboard.greetingStudentDay',
  evening: 'dashboard.greetingStudentEvening',
}

function StudentDashboard({ name }: { name: string }) {
  const { t } = useTranslation()
  const config = use(configPromise)
  const phase = config.current_phase
  const greeting = t(STUDENT_GREETING_KEYS[getTimeOfDay()], { name: getFirstName(name) })
  const [selectionPromise] = useState(fetchStudentSelection)

  if (phase === 'preparation') {
    return (
      <div className={styles.card}>
        <img className={styles.banner} src={mainImage} alt="" />
        <h2 className={styles.greeting}>{greeting}</h2>
        <p className={styles.subtitle}>{t('dashboard.prepIntro')}</p>
        <p className={styles.subtitle}>
          {config.selection_phase_start
            ? <Trans i18nKey="dashboard.prepSelectionInfo" values={{ date: formatPhaseDate(config.selection_phase_start) }} components={{ strong: <strong /> }} />
            : t('dashboard.prepSelectionInfoUnknown')}
        </p>
        <p className={styles.subtitle}>
          {config.conference_phase_start
            ? <Trans i18nKey="dashboard.prepConferenceInfo" values={{ date: formatPhaseDate(config.conference_phase_start) }} components={{ strong: <strong /> }} />
            : t('dashboard.prepConferenceInfoUnknown')}
        </p>
      </div>
    )
  }

  if (phase === 'conference') {
    const actions = t('dashboard.studentConferenceActions', { returnObjects: true }) as string[]
    return (
      <div className={styles.card}>
        <img className={styles.banner} src={mainImage} alt="" />
        <h2 className={styles.greeting}>{greeting}</h2>
        <p className={styles.subtitle}>{t('dashboard.phaseConference')}</p>
        <ul className={styles.actionList}>
          {actions.map((item, i) => <li key={i}>{item}</li>)}
        </ul>
      </div>
    )
  }

  // selection phase
  return (
    <div className={styles.card}>
      <img className={styles.banner} src={mainImage} alt="" />
      <h2 className={styles.greeting}>{greeting}</h2>
      <Suspense fallback={null}>
        <StudentSelectionSummary config={config} selectionPromise={selectionPromise} />
      </Suspense>
      <Link to="/select-tags" className={styles.primaryBtn}>{t('dashboard.selectTagsButton')}</Link>
    </div>
  )
}

function StudentSelectionSummary({
  config,
  selectionPromise,
}: {
  config: AppConfig
  selectionPromise: Promise<StudentSelectionData>
}) {
  const { t } = useTranslation()
  const selection = use(selectionPromise)
  const count = selection.tag_ids.length

  const startDate = config.selection_phase_start ? formatPhaseDate(config.selection_phase_start) : null
  const endDate = config.conference_phase_start ? formatPhaseDate(config.conference_phase_start) : null

  return (
    <>
      <p className={styles.subtitle}>
        {startDate && endDate
          ? <Trans i18nKey="dashboard.selectionPhaseInfo" values={{ startDate, endDate }} components={{ strong: <strong /> }} />
          : startDate
            ? <Trans i18nKey="dashboard.selectionPhaseInfoNoEnd" values={{ startDate }} components={{ strong: <strong /> }} />
            : t('dashboard.selectionPhaseInfoUnknown')}
      </p>
      <p className={styles.subtitle}>
        {count > 0 ? t('dashboard.selectionCount', { count }) : t('dashboard.selectionCountNone')}
      </p>      
    </>
  )
}

function ConsultantDashboard({ name }: { name: string }) {
  const { t } = useTranslation()
  const config = use(configPromise)
  const isConference = config.current_phase === 'conference'

  return (
    <div className={styles.cardWide}>
      <h2 className={styles.greeting}>{t('dashboard.greetingConsultant', { name })}</h2>
      {isConference ? (
        <>
          <p className={styles.subtitle}>{t('dashboard.consultantPhaseConference')}</p>
          <ul className={styles.actionList}>
            {(t('dashboard.consultantConferenceActions', { returnObjects: true }) as string[])
              .map((item, i) => <li key={i}>{item}</li>)}
          </ul>
        </>
      ) : (
        <p className={styles.subtitle}>{t('dashboard.consultantIntro')}</p>
      )}
      <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
        <ConsultantTabs config={config} sessionReadOnly={isConference} />
      </Suspense>
    </div>
  )
}

type ConsultantTab = 'session' | 'profile'

function ConsultantTabs({ config, sessionReadOnly }: { config: AppConfig; sessionReadOnly: boolean }) {
  const { t } = useTranslation()
  const [activeTab, setActiveTab] = useState<ConsultantTab>('session')
  const [sessionPromise] = useState(fetchConsultantSession)
  const [slotOptionsPromise] = useState(fetchSlotOptions)
  const [profilePromise] = useState(fetchConsultantProfile)
  const [seriesPromise] = useState(fetchSeries)

  return (
    <>
      <div className={styles.tabs} style={{ marginTop: '1.25rem' }}>
        <button
          className={`${styles.tab} ${activeTab === 'session' ? styles.tabActive : ''}`}
          onClick={() => setActiveTab('session')}
        >
          {t('session.title')}
        </button>
        <button
          className={`${styles.tab} ${activeTab === 'profile' ? styles.tabActive : ''}`}
          onClick={() => setActiveTab('profile')}
        >
          {t('profile.title')}
        </button>
      </div>

      <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
        {activeTab === 'session'
          ? <SessionTabContent sessionPromise={sessionPromise} slotOptionsPromise={slotOptionsPromise} readOnly={sessionReadOnly} />
          : <ProfileTabContent profilePromise={profilePromise} seriesPromise={seriesPromise} config={config} />
        }
      </Suspense>
    </>
  )
}

function SessionTabContent({
  sessionPromise,
  slotOptionsPromise,
  readOnly,
}: {
  sessionPromise: ReturnType<typeof fetchConsultantSession>
  slotOptionsPromise: ReturnType<typeof fetchSlotOptions>
  readOnly: boolean
}) {
  const { t } = useTranslation()
  const initial = use(sessionPromise)
  const slotOptions = use(slotOptionsPromise)
  const slotGroups = buildSlotGroups(slotOptions, t)
  return readOnly
    ? <SessionReadOnly session={initial} slotGroups={slotGroups} />
    : <SessionForm initial={initial} slotGroups={slotGroups} />
}

function ProfileTabContent({
  profilePromise,
  seriesPromise,
  config,
}: {
  profilePromise: ReturnType<typeof fetchConsultantProfile>
  seriesPromise: ReturnType<typeof fetchSeries>
  config: AppConfig
}) {
  const initial = use(profilePromise)
  const seriesOptions = use(seriesPromise)
  return <ProfileForm initial={initial} seriesOptions={seriesOptions} graduationYearRange={config.graduation_year_range} />
}

function AdminDashboard({ name }: { name: string }) {
  const { t } = useTranslation()

  const navItems: { label: string; to: string }[] = [
    { label: t('admin.studentsOverview'), to: '/admin/students' },
    { label: t('admin.consultantsOverview'), to: '/admin/consultants' },
    { label: t('admin.topicsOverview'), to: '/admin/topics' },
    { label: t('admin.eventSection'), to: '/admin/event' },
    { label: t('admin.settingsOverview'), to: '/admin/users' },
  ]

  return (
    <div className={styles.card}>
      <h2 className={styles.greeting}>{t('dashboard.greetingAdmin', { name })}</h2>
      <p className={styles.subtitle}>{t('dashboard.adminSubtitle')}</p>

      <div className={styles.adminNav}>
        {navItems.map(item => (
          <Link key={item.to} to={item.to} className={styles.adminNavCard}>
            {item.label}
          </Link>
        ))}
      </div>
    </div>
  )
}
