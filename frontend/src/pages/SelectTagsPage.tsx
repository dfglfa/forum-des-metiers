import { Suspense, use, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { buildSlotGroups } from '../api/session'
import type { ConsultantSession } from '../api/session'
import { fetchSlotOptions } from '../api/slotOptions'
import type { SlotOption } from '../api/slotOptions'
import { fetchStudentTopics } from '../api/studentTopics'
import type { StudentTopic } from '../api/studentTopics'
import { fetchStudentTags } from '../api/studentTags'
import type { StudentTag } from '../api/studentTags'
import { fetchStudentSelection, saveStudentSelection, MIN_TAG_SELECTIONS, MAX_TAG_SELECTIONS } from '../api/studentSelection'
import type { StudentSelectionData } from '../api/studentSelection'
import { fetchConfig } from '../api/config'
import type { AppConfig } from '../api/config'
import { SessionReadOnly } from './ConsultantSessionPage'
import styles from './SelectTagsPage.module.css'
import TopBar from '../components/TopBar'

function AddCircleIcon() {
  return (
    <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true">
      <circle cx="10" cy="10" r="9" fill="none" stroke="currentColor" strokeWidth="1.5" />
      <path d="M10 6v8M6 10h8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  )
}

function reorder(ids: number[], fromIndex: number, toIndex: number): number[] {
  const next = [...ids]
  const [moved] = next.splice(fromIndex, 1)
  next.splice(Math.min(toIndex, next.length), 0, moved)
  return next
}

type SlotGroups = ReturnType<typeof buildSlotGroups>

function TagBrowserContent({
  dataPromise,
}: {
  dataPromise: Promise<[StudentTag[], StudentSelectionData, StudentTopic[], SlotOption[]]>
}) {
  const { t } = useTranslation()
  const [tags, initialSelection, topics, slotOptions] = use(dataPromise)
  const slotGroups = buildSlotGroups(slotOptions, t)
  const topicsById = new Map(topics.map(topic => [topic.id, topic]))

  const [selectedIds, setSelectedIds] = useState<number[]>(initialSelection.tag_ids)
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [dragIndex, setDragIndex] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const tagsById = new Map(tags.map(tag => [tag.id, tag]))
  const availableTags = tags.filter(tag => !selectedIds.includes(tag.id))

  function selectTag(tagId: number) {
    setSuccess(false)
    setSelectedIds(prev => (prev.length < MAX_TAG_SELECTIONS ? [...prev, tagId] : prev))
    setExpandedId(prev => (prev === tagId ? null : prev))
  }

  function removeTag(tagId: number) {
    setSuccess(false)
    setSelectedIds(prev => prev.filter(id => id !== tagId))
  }

  function handleDrop(targetIndex: number) {
    if (dragIndex === null || dragIndex === targetIndex) {
      setDragIndex(null)
      return
    }
    setSuccess(false)
    setSelectedIds(prev => reorder(prev, dragIndex, targetIndex))
    setDragIndex(null)
  }

  const canSave = selectedIds.length >= MIN_TAG_SELECTIONS && selectedIds.length <= MAX_TAG_SELECTIONS

  async function handleSave() {
    if (!canSave) return
    setBusy(true)
    setSuccess(false)
    setError(null)
    try {
      await saveStudentSelection(selectedIds)
      setSuccess(true)
    } catch {
      setError(t('dashboard.selectionErrorSave'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className={styles.selectionLayout}>
      <div className={styles.column}>
        <h2 className={styles.columnTitle}>{t('dashboard.availableTagsTitle')}</h2>
        <p className={styles.columnHint}>{t('dashboard.availableTagsHint')}</p>
        <div className={styles.topicList}>
          {availableTags.map(tag => (
            <TagRow
              key={tag.id}
              tag={tag}
              expanded={expandedId === tag.id}
              onExpandToggle={() => setExpandedId(expandedId === tag.id ? null : tag.id)}
              topicsById={topicsById}
              slotGroups={slotGroups}
              trailing={
                <button
                  type="button"
                  className={styles.addBtn}
                  disabled={selectedIds.length >= MAX_TAG_SELECTIONS}
                  onClick={e => { e.stopPropagation(); selectTag(tag.id) }}
                  aria-label={t('dashboard.selectionAddAria')}
                >
                  <AddCircleIcon />
                </button>
              }
            />
          ))}
          {tags.length === 0 && <p className={styles.soonToCome}>{t('dashboard.selectionNoTags')}</p>}
          {tags.length > 0 && availableTags.length === 0 && (
            <p className={styles.soonToCome}>{t('dashboard.selectionAllSelected')}</p>
          )}
        </div>
      </div>

      <div className={styles.column}>
        <h2 className={styles.columnTitle}>{t('dashboard.selectedTagsTitle')}</h2>
        <p className={styles.columnHint}>{t('dashboard.selectedTagsHint')}</p>
        <div className={styles.slotList}>
          {Array.from({ length: MAX_TAG_SELECTIONS }).map((_, index) => {
            const tagId = selectedIds[index]
            const tag = tagId != null ? tagsById.get(tagId) : undefined
            return tag ? (
              <SelectedTagSlotRow
                key={tagId}
                index={index}
                tag={tag}
                dragging={dragIndex === index}
                expanded={expandedId === tag.id}
                onExpandToggle={() => setExpandedId(expandedId === tag.id ? null : tag.id)}
                onRemove={() => removeTag(tag.id)}
                onDragStart={() => setDragIndex(index)}
                onDragEnd={() => setDragIndex(null)}
                onDrop={() => handleDrop(index)}
                topicsById={topicsById}
                slotGroups={slotGroups}
              />
            ) : (
              <div
                key={`empty-${index}`}
                className={styles.emptySlot}
                onDragOver={e => e.preventDefault()}
                onDrop={() => handleDrop(index)}
              >
                <span className={styles.slotRank}>{index + 1}</span>
                <span>{t('dashboard.selectionEmptySlot')}</span>
              </div>
            )
          })}
        </div>
        <div className={styles.footer}>
          <button className={styles.saveBtn} onClick={handleSave} disabled={busy || !canSave}>
            {busy ? t('dashboard.selectionSaving') : t('dashboard.selectionSave')}
          </button>
        </div>
        {success && <p className={styles.successMsg}>{t('dashboard.selectionSaved')}</p>}
        {error && <p className={styles.errorMsg}>{error}</p>}
      </div>
    </div>
  )
}

function TagRow({
  tag,
  expanded,
  onExpandToggle,
  topicsById,
  slotGroups,
  trailing,
}: {
  tag: StudentTag
  expanded: boolean
  onExpandToggle: () => void
  topicsById: Map<number, StudentTopic>
  slotGroups: SlotGroups
  trailing: React.ReactNode
}) {
  const { t } = useTranslation()

  return (
    <div className={styles.topicRow}>
      <div className={styles.topicRowHeader} onClick={onExpandToggle}>
        <div className={styles.topicRowInfo}>
          <span className={styles.topicRowTitle}>{tag.name}</span>
          <span className={styles.topicRowConsultant}>{t('dashboard.tagTopicsCount', { count: tag.topics_count })}</span>
        </div>
        {trailing}
      </div>
      {expanded && <TagDetail tag={tag} topicsById={topicsById} slotGroups={slotGroups} />}
    </div>
  )
}

function SelectedTagSlotRow({
  index,
  tag,
  dragging,
  expanded,
  onExpandToggle,
  onRemove,
  onDragStart,
  onDragEnd,
  onDrop,
  topicsById,
  slotGroups,
}: {
  index: number
  tag: StudentTag
  dragging: boolean
  expanded: boolean
  onExpandToggle: () => void
  onRemove: () => void
  onDragStart: () => void
  onDragEnd: () => void
  onDrop: () => void
  topicsById: Map<number, StudentTopic>
  slotGroups: SlotGroups
}) {
  const { t } = useTranslation()

  return (
    <div
      className={`${styles.topicRow} ${styles.slotRowFilled} ${dragging ? styles.slotRowDragging : ''}`}
      draggable
      onDragStart={onDragStart}
      onDragEnd={onDragEnd}
      onDragOver={e => e.preventDefault()}
      onDrop={onDrop}
    >
      <div className={styles.topicRowHeader} onClick={onExpandToggle}>
        <span className={styles.dragHandle}>⋮⋮</span>
        <span className={styles.slotRank}>{index + 1}</span>
        <div className={styles.topicRowInfo}>
          <span className={styles.topicRowTitle}>{tag.name}</span>
          <span className={styles.topicRowConsultant}>{t('dashboard.tagTopicsCount', { count: tag.topics_count })}</span>
        </div>
        <button
          type="button"
          className={styles.removeBtn}
          onClick={e => { e.stopPropagation(); onRemove() }}
          aria-label={t('dashboard.selectionRemoveAria')}
        >
          ×
        </button>
      </div>
      {expanded && <TagDetail tag={tag} topicsById={topicsById} slotGroups={slotGroups} />}
    </div>
  )
}

// Expanded tag panel: description + the talks currently filed under this tag. Clicking a talk
// opens the same detail view (Vortrag/Profil tabs) a student would see when browsing directly.
function TagDetail({
  tag,
  topicsById,
  slotGroups,
}: {
  tag: StudentTag
  topicsById: Map<number, StudentTopic>
  slotGroups: SlotGroups
}) {
  const { t } = useTranslation()
  const [expandedTopicId, setExpandedTopicId] = useState<number | null>(null)

  return (
    <div className={styles.topicDetail}>
      {tag.description && <p className={styles.columnHint}>{tag.description}</p>}
      {tag.topics.length > 0 ? (
        <div className={styles.topicList}>
          {tag.topics.map(topicSummary => {
            const topic = topicsById.get(topicSummary.id)
            const isExpanded = expandedTopicId === topicSummary.id
            return (
              <div key={topicSummary.id} className={styles.topicRow}>
                <div
                  className={styles.topicRowHeader}
                  onClick={() => setExpandedTopicId(isExpanded ? null : topicSummary.id)}
                >
                  <div className={styles.topicRowInfo}>
                    <span className={styles.topicRowTitle}>{topicSummary.title}</span>
                    {topicSummary.consultant && <span className={styles.topicRowConsultant}>{topicSummary.consultant.name}</span>}
                  </div>
                </div>
                {isExpanded && topic && <TalkDetailTabs topic={topic} slotGroups={slotGroups} />}
              </div>
            )
          })}
        </div>
      ) : (
        <p className={styles.noData}>{t('dashboard.tagNoTopicsYet')}</p>
      )}
    </div>
  )
}

type TalkDetailTab = 'session' | 'profile'

function TalkDetailTabs({ topic, slotGroups }: { topic: StudentTopic; slotGroups: SlotGroups }) {
  const { t } = useTranslation()
  const [tab, setTab] = useState<TalkDetailTab>('session')

  const session: ConsultantSession = {
    id: topic.id,
    title: topic.title,
    description: topic.description,
    selected_slots: topic.selected_slots,
    tag: topic.tag,
    time_slots: topic.time_slots,
  }

  return (
    <div className={styles.topicDetail}>
      <div className={styles.tabs}>
        <button
          className={`${styles.tab} ${tab === 'session' ? styles.tabActive : ''}`}
          onClick={() => setTab('session')}
        >
          {t('session.title')}
        </button>
        <button
          className={`${styles.tab} ${tab === 'profile' ? styles.tabActive : ''}`}
          onClick={() => setTab('profile')}
        >
          {t('profile.title')}
        </button>
      </div>
      {tab === 'session'
        ? <SessionReadOnly session={session} slotGroups={slotGroups} hideTag />
        : <StudentProfileView profile={topic.consultant.consultant_profile} />
      }
    </div>
  )
}

function Field({ label, value }: { label: string; value: string | number | null | undefined }) {
  return (
    <div className={styles.field}>
      <span className={styles.label}>{label}</span>
      {value != null && value !== ''
        ? <span className={styles.value}>{value}</span>
        : <span className={styles.valueEmpty}>—</span>
      }
    </div>
  )
}

function StudentProfileView({ profile }: { profile: StudentTopic['consultant']['consultant_profile'] }) {
  const { t } = useTranslation()

  if (!profile) {
    return <p className={styles.noData}>{t('admin.consultantDetail.noProfile')}</p>
  }

  const fullName = [profile.first_name, profile.last_name].filter(Boolean).join(' ')

  return (
    <div className={styles.card}>
      <div className={styles.section}>
        <div className={styles.photoRow}>
          {profile.profile_picture_url
            ? <img src={profile.profile_picture_url} alt="" className={styles.avatar} />
            : <div className={styles.avatarPlaceholder}>👤</div>
          }
          <span className={styles.profileName}>{fullName}</span>
        </div>
      </div>

      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('profile.sectionPersonal')}</p>
        <div className={styles.row}>
          <Field label={t('profile.fieldGraduationYear')} value={profile.graduation_year} />
          <Field label={t('profile.fieldSerie')} value={profile.serie} />
        </div>
        <div className={styles.row} style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldLinkedin')} value={profile.linkedin_url} />
          <Field label={t('profile.fieldLanguage')} value={profile.language ? t(`lang.${profile.language}`) : null} />
        </div>
      </div>

      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('profile.sectionCareer')}</p>
        <Field label={t('profile.fieldCareerPath')} value={profile.career_path} />
        <div style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldCurrentSituation')} value={profile.current_situation} />
        </div>
        <div style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldWhyThisCareer')} value={profile.why_this_career} />
        </div>
      </div>
    </div>
  )
}

export default function SelectTagsPage() {
  const [configPromise] = useState(fetchConfig)

  return (
    <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
      <SelectTagsPageContent configPromise={configPromise} />
    </Suspense>
  )
}

function SelectTagsPageContent({ configPromise }: { configPromise: Promise<AppConfig> }) {
  const { t } = useTranslation()
  const config = use(configPromise)
  const [dataPromise] = useState(() => Promise.all([
    fetchStudentTags(),
    fetchStudentSelection(),
    fetchStudentTopics(),
    fetchSlotOptions(),
  ]))

  if (config.current_phase === 'preparation') {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className={styles.page}>
      <TopBar backTo="/dashboard" backLabel={t('admin.backToDashboard')} />
      <main className={styles.main}>
        <h1 className={styles.title}>{t('dashboard.selectTagsTitle')}</h1>
        <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
          <TagBrowserContent dataPromise={dataPromise} />
        </Suspense>
      </main>
    </div>
  )
}
