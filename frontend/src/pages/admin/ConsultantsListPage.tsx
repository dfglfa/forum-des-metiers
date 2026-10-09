import { Suspense, use, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { fetchAdminConsultants, fetchAdminTags, updateTopicTag } from '../../api/admin'
import type { AdminConsultantListItem, AdminConsultantTopic, Tag } from '../../api/admin'
import { useSortableData } from '../../hooks/useSortableData'
import SortableHeader from '../../components/SortableHeader'
import styles from './AdminListPage.module.css'
import TopBar from '../../components/TopBar'

type ConsultantColumn = 'name' | 'email' | 'tag' | 'language' | 'activated'

function TagCell({
  topic,
  tags,
  onTagChange,
}: {
  topic: AdminConsultantTopic | undefined
  tags: Tag[]
  onTagChange: (topicId: number, tag: Tag) => void
}) {
  if (!topic) return <>—</>
  return <EditableTagCell topic={topic} tags={tags} onTagChange={onTagChange} />
}

function EditableTagCell({
  topic,
  tags,
  onTagChange,
}: {
  topic: AdminConsultantTopic
  tags: Tag[]
  onTagChange: (topicId: number, tag: Tag) => void
}) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState(false)
  const [selectedTagId, setSelectedTagId] = useState<number | ''>(topic.tag?.id ?? '')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSave() {
    if (!selectedTagId) return
    setBusy(true)
    setError(null)
    try {
      const updated = await updateTopicTag(topic.id, selectedTagId)
      if (updated.tag) onTagChange(topic.id, updated.tag)
      setEditing(false)
    } catch {
      setError(t('admin.consultantDetail.errorTagSave'))
    } finally {
      setBusy(false)
    }
  }

  function handleCancel() {
    setSelectedTagId(topic.tag?.id ?? '')
    setError(null)
    setEditing(false)
  }

  if (editing) {
    return (
      <>
        <div className={styles.tagEditRow}>
          <select
            value={selectedTagId}
            onChange={e => setSelectedTagId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">—</option>
            {tags.map(tag => <option key={tag.id} value={tag.id}>{tag.name}</option>)}
          </select>
          <button className={styles.tagSaveBtn} onClick={handleSave} disabled={busy || !selectedTagId}>
            {busy ? '…' : t('admin.consultantDetail.saveTag')}
          </button>
          <button className={styles.tagCancelBtn} onClick={handleCancel} disabled={busy}>
            {t('admin.phase.cancel')}
          </button>
        </div>
        {error && <p className={styles.tagError}>{error}</p>}
      </>
    )
  }

  return (
    <button
      type="button"
      className={styles.tagPencilBtn}
      onClick={() => setEditing(true)}
      aria-label={t('admin.consultantDetail.editTag')}
    >
      {topic.tag?.name ?? '—'}
      <span className={styles.tagPencilIcon} aria-hidden="true">✏️</span>
    </button>
  )
}

function ConsultantTable({
  dataPromise,
  tagsPromise,
}: {
  dataPromise: Promise<AdminConsultantListItem[]>
  tagsPromise: Promise<Tag[]>
}) {
  const initial = use(dataPromise)
  const tags = use(tagsPromise)
  const { t } = useTranslation()
  const [consultants, setConsultants] = useState(initial)
  const { sorted, sortKey, direction, requestSort } = useSortableData<AdminConsultantListItem, ConsultantColumn>(consultants, {
    name: c => c.name,
    email: c => c.email,
    tag: c => c.topics[0]?.tag?.name ?? null,
    language: c => c.consultant_profile?.language ?? null,
    activated: c => c.email_verified_at ? 1 : 0,
  })

  function handleTagChange(topicId: number, tag: Tag) {
    setConsultants(prev => prev.map(c =>
      c.topics[0]?.id === topicId
        ? { ...c, topics: [{ ...c.topics[0], tag }, ...c.topics.slice(1)] }
        : c
    ))
  }

  if (consultants.length === 0) {
    return <p className={styles.empty}>{t('admin.noData')}</p>
  }

  return (
    <table className={styles.table}>
      <thead>
        <tr>
          <th className={styles.avatarCell}></th>
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.name')} sortKey="name" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.email')} sortKey="email" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.tag')} sortKey="tag" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.language')} sortKey="language" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.status')} sortKey="activated" activeKey={sortKey} direction={direction} onSort={requestSort} />
        </tr>
      </thead>
      <tbody>
        {sorted.map(c => (
          <tr key={c.id}>
            <td className={styles.avatarCell}>
              {c.consultant_profile?.profile_picture_url
                ? <img src={c.consultant_profile.profile_picture_url} alt="" className={styles.avatar} />
                : <div className={styles.avatarPlaceholder}>👤</div>
              }
            </td>
            <td><Link to={`/admin/consultants/${c.id}`}>{c.name}</Link></td>
            <td>{c.email ?? '—'}</td>
            <td><TagCell topic={c.topics[0]} tags={tags} onTagChange={handleTagChange} /></td>
            <td>{c.consultant_profile?.language ? t(`lang.${c.consultant_profile.language}`) : '—'}</td>
            <td>
              <span className={c.email_verified_at ? styles.badgeActive : styles.badgePending}>
                {c.email_verified_at ? t('admin.columns.activatedYes') : t('admin.columns.activatedNo')}
              </span>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

export default function ConsultantsListPage() {
  const { t } = useTranslation()
  const [dataPromise] = useState(() => fetchAdminConsultants())
  const [tagsPromise] = useState(() => fetchAdminTags())

  return (
    <div className={styles.page}>
      <TopBar backTo="/dashboard" backLabel={t('admin.backToDashboard')} />
      <main className={styles.main}>
        <div className={styles.titleRow}>
          <h1 className={styles.title} style={{ margin: 0 }}>{t('admin.consultantsOverview')}</h1>
          <div className={styles.actions}>
            <Link to="/admin/invite" className={styles.primaryBtn}>{t('admin.inviteSpeaker')}</Link>
            <Link to="/admin/invite/bulk" className={styles.secondaryBtn}>{t('admin.bulkInviteSpeakers')}</Link>
          </div>
        </div>
        <Suspense fallback={<p className={styles.empty}>…</p>}>
          <ConsultantTable dataPromise={dataPromise} tagsPromise={tagsPromise} />
        </Suspense>
      </main>
    </div>
  )
}
