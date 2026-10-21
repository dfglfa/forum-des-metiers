import { Suspense, use, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { deleteConsultant, fetchAdminConsultants, fetchAdminTags, setConsultantTag, updateConsultantParticipation, updateTopicTag } from '../../api/admin'
import type { AdminConsultantListItem, AdminConsultantTopic, Tag } from '../../api/admin'
import { useSortableData } from '../../hooks/useSortableData'
import SortableHeader from '../../components/SortableHeader'
import ConfirmDialog from '../../components/ConfirmDialog'
import styles from './AdminListPage.module.css'
import TopBar from '../../components/TopBar'

type PageSize = 25 | 50 | 'all'

type ConsultantColumn = 'name' | 'email' | 'tag' | 'language' | 'activated' | 'participating'

function EditableTagCell({
  consultantId,
  topic,
  tags,
  onTagChange,
}: {
  consultantId: number
  topic: AdminConsultantTopic | undefined
  tags: Tag[]
  onTagChange: (consultantId: number, topic: AdminConsultantTopic) => void
}) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState(false)
  const [selectedTagId, setSelectedTagId] = useState<number | ''>(topic?.tag?.id ?? '')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSave() {
    if (!selectedTagId) return
    setBusy(true)
    setError(null)
    try {
      const updated = topic
        ? await updateTopicTag(topic.id, selectedTagId)
        : await setConsultantTag(consultantId, selectedTagId)
      onTagChange(consultantId, updated)
      setEditing(false)
    } catch {
      setError(t('admin.consultantDetail.errorTagSave'))
    } finally {
      setBusy(false)
    }
  }

  function handleCancel() {
    setSelectedTagId(topic?.tag?.id ?? '')
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
      {topic?.tag?.name ?? '—'}
      <span className={styles.tagPencilIcon} aria-hidden="true">✏️</span>
    </button>
  )
}

function ParticipationBadge({ consultant }: { consultant: AdminConsultantListItem }) {
  const { t } = useTranslation()
  return (
    <span className={consultant.is_participating ? styles.badgeActive : styles.badgeNo}>
      {consultant.is_participating ? t('admin.consultantDetail.listYes') : t('admin.consultantDetail.listNo')}
    </span>
  )
}

function RowActionsMenu({
  consultant,
  onParticipationChanged,
  onDeleted,
}: {
  consultant: AdminConsultantListItem
  onParticipationChanged: (id: number, isParticipating: boolean) => void
  onDeleted: (id: number) => void
}) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [confirmingNotParticipating, setConfirmingNotParticipating] = useState(false)
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    function handleClickOutside(e: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [open])

  async function setParticipating(value: boolean) {
    setBusy(true)
    setError(null)
    try {
      await updateConsultantParticipation(consultant.id, value)
      onParticipationChanged(consultant.id, value)
      setConfirmingNotParticipating(false)
    } catch {
      setError(t('admin.consultantDetail.errorParticipationSave'))
    } finally {
      setBusy(false)
    }
  }

  async function handleDelete() {
    setBusy(true)
    setError(null)
    try {
      await deleteConsultant(consultant.id)
      onDeleted(consultant.id)
    } catch {
      setError(t('admin.consultantDetail.errorDelete'))
      setBusy(false)
      setConfirmingDelete(false)
    }
  }

  return (
    <div className={styles.rowActions} ref={containerRef}>
      <button
        type="button"
        className={styles.rowActionsTrigger}
        onClick={() => setOpen(o => !o)}
        aria-label={t('admin.consultantDetail.rowActionsLabel')}
        aria-haspopup="menu"
        aria-expanded={open}
      >
        ⋯
      </button>
      {open && (
        <div className={styles.rowActionsMenu} role="menu">
          {consultant.is_participating ? (
            <button
              type="button"
              role="menuitem"
              className={styles.rowActionsItem}
              onClick={() => { setOpen(false); setConfirmingNotParticipating(true) }}
            >
              {t('admin.consultantDetail.markNotParticipating')}
            </button>
          ) : (
            <button
              type="button"
              role="menuitem"
              className={styles.rowActionsItem}
              onClick={() => { setOpen(false); setParticipating(true) }}
            >
              {t('admin.consultantDetail.markParticipating')}
            </button>
          )}
          <button
            type="button"
            role="menuitem"
            className={styles.rowActionsItemDanger}
            onClick={() => { setOpen(false); setConfirmingDelete(true) }}
          >
            {t('admin.consultantDetail.deleteButton')}
          </button>
        </div>
      )}
      {error && <p className={styles.tagError}>{error}</p>}
      {confirmingNotParticipating && (
        <ConfirmDialog
          title={t('admin.consultantDetail.participationDialogTitle', { name: consultant.name })}
          description={t('admin.consultantDetail.participationDialogDesc')}
          warning={t('admin.consultantDetail.participationDialogNote')}
          cancelLabel={t('admin.phase.cancel')}
          confirmLabel={busy ? t('admin.consultantDetail.updatingParticipation') : t('admin.consultantDetail.participationConfirm')}
          busy={busy}
          onCancel={() => setConfirmingNotParticipating(false)}
          onConfirm={() => setParticipating(false)}
        />
      )}
      {confirmingDelete && (
        <ConfirmDialog
          title={t('admin.consultantDetail.deleteDialogTitle', { name: consultant.name })}
          description={t('admin.consultantDetail.deleteDialogDesc')}
          warning={t('admin.consultantDetail.deleteDialogWarning')}
          cancelLabel={t('admin.phase.cancel')}
          confirmLabel={busy ? t('admin.consultantDetail.deleting') : t('admin.consultantDetail.deleteConfirm')}
          busy={busy}
          onCancel={() => setConfirmingDelete(false)}
          onConfirm={handleDelete}
        />
      )}
    </div>
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
  const [search, setSearch] = useState('')
  const [pageSize, setPageSize] = useState<PageSize>(25)
  const [page, setPage] = useState(0)

  const query = search.trim().toLowerCase()
  const filtered = useMemo(() => query
    ? consultants.filter(c => c.name?.toLowerCase().includes(query))
    : consultants,
  [consultants, query])

  const { sorted, sortKey, direction, requestSort } = useSortableData<AdminConsultantListItem, ConsultantColumn>(filtered, {
    name: c => c.name,
    email: c => c.email,
    tag: c => c.topics[0]?.tag?.name ?? null,
    language: c => c.consultant_profile?.language ?? null,
    activated: c => c.email_verified_at ? 1 : 0,
    participating: c => c.is_participating ? 1 : 0,
  })

  function handleTagChange(consultantId: number, topic: AdminConsultantTopic) {
    setConsultants(prev => prev.map(c =>
      c.id === consultantId
        ? { ...c, topics: [{ ...c.topics[0], ...topic }, ...c.topics.slice(1)] }
        : c
    ))
  }

  function handleDeleted(id: number) {
    setConsultants(prev => prev.filter(c => c.id !== id))
  }

  function handleParticipationChanged(id: number, isParticipating: boolean) {
    setConsultants(prev => prev.map(c => c.id === id ? { ...c, is_participating: isParticipating } : c))
  }

  if (consultants.length === 0) {
    return <p className={styles.empty}>{t('admin.noData')}</p>
  }

  const confirmed = consultants.filter(c => c.email_verified_at).length

  const pageCount = pageSize === 'all' ? 1 : Math.max(1, Math.ceil(sorted.length / pageSize))
  const currentPage = Math.min(page, pageCount - 1)
  const visible = pageSize === 'all'
    ? sorted
    : sorted.slice(currentPage * pageSize, (currentPage + 1) * pageSize)

  const paginationControls = (
  <div className={styles.pagination}>
      <label>
        {t('admin.perPage')}{' '}
        <select
          value={pageSize}
          onChange={e => {
            setPageSize(e.target.value === 'all' ? 'all' : Number(e.target.value) as PageSize)
            setPage(0)
          }}
        >
          <option value={25}>25</option>
          <option value={50}>50</option>
          <option value="all">{t('admin.all')}</option>
        </select>
      </label>
      {pageCount > 1 && (
        <>
          <button type="button" onClick={() => setPage(currentPage - 1)} disabled={currentPage === 0}>‹</button>
          <span>{t('admin.pageOf', { page: currentPage + 1, total: pageCount })}</span>
          <button type="button" onClick={() => setPage(currentPage + 1)} disabled={currentPage >= pageCount - 1}>›</button>
        </>
      )}
    </div>
  )

  return (
    <>
    <input
      type="search"
      className={styles.searchInput}
      value={search}
      onChange={e => { setSearch(e.target.value); setPage(0) }}
      placeholder={t('admin.searchByName')}
      aria-label={t('admin.searchByName')}
    />
    <p className={styles.summary}>
      {t('admin.consultantsSummary', { count: consultants.length, confirmed })}
    </p>
    {paginationControls}
    {sorted.length === 0 ? <p className={styles.empty}>{t('admin.noData')}</p> : (
    <table className={styles.table}>
      <thead>
        <tr>
          <th className={styles.avatarCell}></th>
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.name')} sortKey="name" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.email')} sortKey="email" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.tag')} sortKey="tag" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.language')} sortKey="language" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.status')} sortKey="activated" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <SortableHeader className={styles.sortableTh} label={t('admin.columns.participation')} sortKey="participating" activeKey={sortKey} direction={direction} onSort={requestSort} />
          <th></th>
        </tr>
      </thead>
      <tbody>
        {visible.map(c => (
          <tr key={c.id}>
            <td className={styles.avatarCell}>
              {c.consultant_profile?.profile_picture_url
                ? <img src={c.consultant_profile.profile_picture_url} alt="" className={styles.avatar} />
                : <div className={styles.avatarPlaceholder}>👤</div>
              }
            </td>
            <td><Link to={`/admin/consultants/${c.id}`}>{c.name}</Link></td>
            <td>{c.email ?? '—'}</td>
            <td><EditableTagCell consultantId={c.id} topic={c.topics[0]} tags={tags} onTagChange={handleTagChange} /></td>
            <td>{c.consultant_profile?.language ? t(`lang.${c.consultant_profile.language}`) : '—'}</td>
            <td>
              <span className={c.email_verified_at ? styles.badgeActive : styles.badgePending}>
                {c.email_verified_at ? t('admin.columns.activatedYes') : t('admin.columns.activatedNo')}
              </span>
            </td>
            <td><ParticipationBadge consultant={c} /></td>
            <td><RowActionsMenu consultant={c} onParticipationChanged={handleParticipationChanged} onDeleted={handleDeleted} /></td>
          </tr>
        ))}
      </tbody>
    </table>
    )}
    {paginationControls}
    </>
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
