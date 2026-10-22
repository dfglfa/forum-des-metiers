import { Suspense, use, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { deleteConsultant, fetchAdminConsultantDetail, fetchAdminTags, setConsultantTag, updateConsultantParticipation, updateTopicTag } from '../../api/admin'
import type { AdminConsultantDetail, AdminConsultantTopic, Tag } from '../../api/admin'
import { buildSlotGroups } from '../../api/session'
import type { SlotGroup } from '../../api/session'
import { fetchSlotOptions } from '../../api/slotOptions'
import styles from './ConsultantDetailPage.module.css'
import TopBar from '../../components/TopBar'
import ConfirmDialog from '../../components/ConfirmDialog'

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

function ProfileSection({ consultant }: { consultant: AdminConsultantDetail }) {
  const { t } = useTranslation()
  const p = consultant.consultant_profile

  if (!p) {
    return <div className={styles.section}><p className={styles.noData}>{t('admin.consultantDetail.noProfile')}</p></div>
  }

  return (
    <>
      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('profile.sectionPhoto')}</p>
        <div className={styles.photoRow}>
          {p.profile_picture_url
            ? <img src={p.profile_picture_url} alt="" className={styles.avatar} />
            : <div className={styles.avatarPlaceholder}>👤</div>
          }
        </div>
      </div>

      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('profile.sectionPersonal')}</p>
        <div className={styles.row}>
          <Field label={t('profile.fieldLastName')} value={p.last_name} />
          <Field label={t('profile.fieldFirstName')} value={p.first_name} />
        </div>
        <div className={styles.row} style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldPhone')} value={p.phone} />
          <Field label={t('profile.fieldGraduationYear')} value={p.graduation_year} />
        </div>
        <div className={styles.row} style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldSerie')} value={p.serie} />
          <Field label={t('profile.fieldLinkedin')} value={p.linkedin_url} />
        </div>
        <div className={styles.row} style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldLanguage')} value={p.language ? t(`lang.${p.language}`) : null} />
        </div>
      </div>

      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('profile.sectionCareer')}</p>
        <Field label={t('profile.fieldCareerPath')} value={p.career_path} />
        <div style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldCurrentSituation')} value={p.current_situation} />
        </div>
        <div style={{ marginTop: '0.75rem' }}>
          <Field label={t('profile.fieldWhyThisCareer')} value={p.why_this_career} />
        </div>
      </div>

      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('profile.sectionConsent')}</p>
        <div className={styles.consentRow}>
          <input type="checkbox" checked={p.consent_poster} readOnly className={styles.consentCheck} />
          <span className={styles.value}>{t('profile.consentPoster')}</span>
        </div>
        <div className={styles.consentRow}>
          <input type="checkbox" checked={p.consent_alumni_data} readOnly className={styles.consentCheck} />
          <span className={styles.value}>{t('profile.consentAlumniData')}</span>
        </div>
      </div>
    </>
  )
}

function TagEditor({
  topic,
  consultantId,
  tags,
  onTagChange,
}: {
  topic: AdminConsultantTopic | null
  consultantId: number
  tags: Tag[]
  onTagChange: (topic: AdminConsultantTopic) => void
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
      onTagChange(updated)
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

  return (
    <div className={styles.field} style={{ marginTop: '0.75rem' }}>
      <span className={styles.label}>{t('admin.columns.tag')}</span>
      {editing ? (
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
      ) : (
        <div className={styles.tagEditRow}>
          {topic?.tag
            ? <span className={styles.tag}>{topic.tag.name}</span>
            : <span className={styles.valueEmpty}>—</span>
          }
          <button className={styles.tagEditBtn} onClick={() => setEditing(true)}>
            {t('admin.consultantDetail.editTag')}
          </button>
        </div>
      )}
      {error && <p className={styles.tagError}>{error}</p>}
    </div>
  )
}

function SessionSection({
  topic,
  consultantId,
  tags,
  onTagChange,
  slotGroups,
}: {
  topic: AdminConsultantTopic | null
  consultantId: number
  tags: Tag[]
  onTagChange: (topic: AdminConsultantTopic) => void
  slotGroups: SlotGroup[]
}) {
  const { t } = useTranslation()

  if (!topic) {
    return (
      <div className={styles.section}>
        <p className={styles.noData}>{t('admin.consultantDetail.noSession')}</p>
        <TagEditor topic={null} consultantId={consultantId} tags={tags} onTagChange={onTagChange} />
      </div>
    )
  }

  return (
    <>
      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('session.sectionDetails')}</p>
        <Field label={t('session.fieldTitle')} value={topic.title} />
        <TagEditor topic={topic} consultantId={consultantId} tags={tags} onTagChange={onTagChange} />
        {topic.description && (
          <div style={{ marginTop: '0.75rem' }}>
            <Field label={t('session.fieldDescription')} value={topic.description} />
          </div>
        )}
      </div>

      <div className={styles.section}>
        <p className={styles.sectionTitle}>{t('session.sectionSlots')}</p>
        {slotGroups.map(group => {
          const active = group.slots.filter(s => (topic.selected_slots ?? []).includes(s.id))
          if (active.length === 0) return null
          return (
            <div key={group.key} className={styles.slotGroup}>
              <p className={styles.slotGroupLabel}>{group.label}</p>
              <div className={styles.slotList}>
                {active.map(slot => (
                  <span key={slot.id} className={styles.slotBadge}>{slot.time}</span>
                ))}
              </div>
            </div>
          )
        })}
      </div>
    </>
  )
}

function ParticipationToggle({
  id,
  name,
  isParticipating,
  onChanged,
}: {
  id: number
  name: string
  isParticipating: boolean
  onChanged: (value: boolean) => void
}) {
  const { t } = useTranslation()
  const [confirming, setConfirming] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function setParticipating(value: boolean) {
    setBusy(true)
    setError(null)
    try {
      await updateConsultantParticipation(id, value)
      onChanged(value)
      setConfirming(false)
    } catch {
      setError(t('admin.consultantDetail.errorParticipationSave'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <div className={styles.participationRow}>
        <span className={isParticipating ? styles.badgeActive : styles.badgeNotParticipating}>
          {isParticipating ? t('admin.consultantDetail.participatingYes') : t('admin.consultantDetail.participatingNo')}
        </span>
        {isParticipating ? (
          <button type="button" className={styles.tagEditBtn} onClick={() => setConfirming(true)} disabled={busy}>
            {t('admin.consultantDetail.markNotParticipating')}
          </button>
        ) : (
          <button type="button" className={styles.tagEditBtn} onClick={() => setParticipating(true)} disabled={busy}>
            {busy ? t('admin.consultantDetail.updatingParticipation') : t('admin.consultantDetail.markParticipating')}
          </button>
        )}
      </div>
      {error && <p className={styles.deleteError}>{error}</p>}
      {confirming && (
        <ConfirmDialog
          title={t('admin.consultantDetail.participationDialogTitle', { name })}
          description={t('admin.consultantDetail.participationDialogDesc')}
          warning={t('admin.consultantDetail.participationDialogNote')}
          cancelLabel={t('admin.phase.cancel')}
          confirmLabel={busy ? t('admin.consultantDetail.updatingParticipation') : t('admin.consultantDetail.participationConfirm')}
          busy={busy}
          onCancel={() => setConfirming(false)}
          onConfirm={() => setParticipating(false)}
        />
      )}
    </>
  )
}

function DeleteConsultantButton({ id, name }: { id: number; name: string }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [confirming, setConfirming] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleDelete() {
    setDeleting(true)
    setError(null)
    try {
      await deleteConsultant(id)
      navigate('/admin/consultants')
    } catch {
      setError(t('admin.consultantDetail.errorDelete'))
      setDeleting(false)
    }
  }

  return (
    <>
      <button type="button" className={styles.deleteBtn} onClick={() => setConfirming(true)}>
        {t('admin.consultantDetail.deleteButton')}
      </button>

      {confirming && (
        <ConfirmDialog
          title={t('admin.consultantDetail.deleteDialogTitle', { name })}
          description={t('admin.consultantDetail.deleteDialogDesc')}
          warning={t('admin.consultantDetail.deleteDialogWarning')}
          cancelLabel={t('admin.phase.cancel')}
          confirmLabel={deleting ? t('admin.consultantDetail.deleting') : t('admin.consultantDetail.deleteConfirm')}
          busy={deleting}
          onCancel={() => setConfirming(false)}
          onConfirm={handleDelete}
        />
      )}
      {error && <p className={styles.deleteError}>{error}</p>}
    </>
  )
}

function DetailContent({
  detailPromise,
  tagsPromise,
  slotOptionsPromise,
}: {
  detailPromise: Promise<AdminConsultantDetail>
  tagsPromise: Promise<Tag[]>
  slotOptionsPromise: ReturnType<typeof fetchSlotOptions>
}) {
  const consultant = use(detailPromise)
  const tags = use(tagsPromise)
  const { t } = useTranslation()
  const slotGroups = buildSlotGroups(use(slotOptionsPromise), t)
  const [topic, setTopic] = useState<AdminConsultantTopic | null>(consultant.topics[0] ?? null)
  const [isParticipating, setIsParticipating] = useState(consultant.is_participating)

  return (
    <div className={styles.page}>
      <TopBar backTo="/admin/consultants" backLabel={t('admin.consultantDetail.backToList')} />
      <main className={styles.main}>
        <div className={styles.titleRow}>
          <div>
            <h1 className={styles.title}>{consultant.name}</h1>
            <p className={styles.subtitle}>{consultant.email ?? consultant.ldap_username ?? ''}</p>
          </div>
          <div className={styles.headerActions}>
            <ParticipationToggle
              id={consultant.id}
              name={consultant.name}
              isParticipating={isParticipating}
              onChanged={setIsParticipating}
            />
            <DeleteConsultantButton id={consultant.id} name={consultant.name} />
          </div>
        </div>

        <div className={styles.card}>
          <ProfileSection consultant={consultant} />
          <SessionSection topic={topic} consultantId={consultant.id} tags={tags} onTagChange={setTopic} slotGroups={slotGroups} />
        </div>
      </main>
    </div>
  )
}

export default function ConsultantDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [detailPromise] = useState(() => fetchAdminConsultantDetail(Number(id)))
  const [tagsPromise] = useState(() => fetchAdminTags())
  const [slotOptionsPromise] = useState(() => fetchSlotOptions())

  return (
    <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
      <DetailContent detailPromise={detailPromise} tagsPromise={tagsPromise} slotOptionsPromise={slotOptionsPromise} />
    </Suspense>
  )
}
