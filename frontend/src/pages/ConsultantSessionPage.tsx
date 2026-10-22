import { Suspense, use, useImperativeHandle, useState } from 'react'
import type { Ref } from 'react'
import { Trans, useTranslation } from 'react-i18next'
import {
  fetchConsultantSession,
  updateConsultantSession,
  buildSlotGroups,
} from '../api/session'
import type { ConsultantSession, SlotGroup, SlotId } from '../api/session'
import { fetchSlotOptions } from '../api/slotOptions'
import { fetchConfig } from '../api/config'
import styles from './ConsultantSessionPage.module.css'
import TopBar from '../components/TopBar'
import type { FormHandle } from '../utils/formHandle'

export function SessionForm({
  initial,
  slotGroups,
  hideFooter = false,
  formRef,
}: {
  initial: ConsultantSession | null
  slotGroups: SlotGroup[]
  hideFooter?: boolean
  formRef?: Ref<FormHandle>
}) {
  const { t } = useTranslation()

  const [selectedSlots, setSelectedSlots] = useState<Set<SlotId>>(
    new Set(initial?.selected_slots ?? [])
  )
  const [busy, setBusy] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function toggleSlot(id: SlotId) {
    setSelectedSlots(prev => {
      const next = new Set(prev)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })
  }

  async function save(): Promise<string | null> {
    if (selectedSlots.size === 0) {
      const message = t('session.errorNoSlots')
      setError(message)
      return message
    }
    setBusy(true)
    setSuccess(false)
    setError(null)
    try {
      await updateConsultantSession({
        selected_slots: Array.from(selectedSlots),
      })
      setSuccess(true)
      return null
    } catch {
      const message = t('session.errorSave')
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
    <form onSubmit={handleSubmit} className={styles.card}>
      <div className={styles.section}>
        <div className={styles.field}>
          <div className={styles.tagBadgeRow}>
            <span>{t('session.fieldTag')}</span>
            {initial?.tag
              ? <span className={styles.tagBadge}>{initial.tag.name}</span>
              : <span className={styles.tagBadgeNone}>{t('session.tagNotAssigned')}</span>
            }
          </div>
        </div>
      </div>

      <div className={styles.section}>
        <p className={styles.sectionHint}>{t('session.slotsIntro')}</p>
        {slotGroups.map(group => (
          <div key={group.key} className={styles.slotGroup}>
            <p className={styles.slotGroupLabel}>{group.label}</p>
            <div className={styles.slotList}>
              {group.slots.map(slot => (
                <label key={slot.id} className={styles.slotCheckbox}>
                  <input
                    type="checkbox"
                    checked={selectedSlots.has(slot.id)}
                    onChange={() => toggleSlot(slot.id)}
                  />
                  {slot.time}
                </label>
              ))}
            </div>
          </div>
        ))}
        <p className={styles.sectionHint}>
          <Trans i18nKey="session.inPersonOnlyHint" components={{ mail: <a href="mailto:forumderberufe@dfglfa.net" /> }} />
        </p>
      </div>

      {!hideFooter && (
        <div className={styles.footer}>
          <button type="submit" className={styles.saveBtn} disabled={busy}>
            {busy ? t('session.saving') : t('session.save')}
          </button>
          {success && <span className={styles.successMsg}>{t('session.saved')}</span>}
          {error && <span className={styles.errorMsg}>{error}</span>}
        </div>
      )}
    </form>
  )
}

export function SessionReadOnly({
  session,
  slotGroups,
  hideTag = false,
  bare = false,
}: {
  session: ConsultantSession | null
  slotGroups: SlotGroup[]
  hideTag?: boolean
  bare?: boolean
}) {
  const { t } = useTranslation()

  if (!session) {
    return <p className={styles.tagBadgeNone}>{t('session.noSessionConfigured')}</p>
  }

  const groupsWithActiveSlots = slotGroups
    .map(group => ({ ...group, slots: group.slots.filter(slot => session.selected_slots.includes(slot.id)) }))
    .filter(group => group.slots.length > 0)

  const rooms = Array.from(new Set(
    session.time_slots.map(ts => ts.room).filter((room): room is string => !!room)
  ))
  const roomText = rooms.length > 0 ? rooms.join(', ') : t('session.roomTbd')

  const content = (
    <>
      <div className={styles.section}>
        <div className={styles.field}>
          <label>{t('session.fieldRoom')}</label>
          <p className={styles.readOnlyValue}>{roomText}</p>
        </div>
        {!hideTag && (
          <div className={styles.field}>
            <div className={styles.tagBadgeRow}>
              <span>{t('session.fieldTag')}</span>
              {session.tag
                ? <span className={styles.tagBadge}>{session.tag.name}</span>
                : <span className={styles.tagBadgeNone}>{t('session.tagNotAssigned')}</span>
              }
            </div>
          </div>
        )}
      </div>

      <div className={styles.section}>
        {groupsWithActiveSlots.map(group => (
          <div key={group.key} className={styles.slotGroup}>
            <p className={styles.slotGroupLabel}>{group.label}</p>
            <div className={styles.slotList}>
              {group.slots.map(slot => (
                <span key={slot.id} className={styles.slotBadgeReadOnly}>{slot.time}</span>
              ))}
            </div>
          </div>
        ))}
        <p className={styles.sectionHint}>
          <Trans i18nKey="session.inPersonOnlyHint" components={{ mail: <a href="mailto:forumderberufe@dfglfa.net" /> }} />
        </p>
      </div>
    </>
  )

  return bare ? content : <div className={styles.card}>{content}</div>
}

function SessionPageContent({
  sessionPromise,
  slotOptionsPromise,
  configPromise,
}: {
  sessionPromise: Promise<ConsultantSession | null>
  slotOptionsPromise: ReturnType<typeof fetchSlotOptions>
  configPromise: ReturnType<typeof fetchConfig>
}) {
  const initial = use(sessionPromise)
  const slotOptions = use(slotOptionsPromise)
  const config = use(configPromise)
  const { t } = useTranslation()
  const slotGroups = buildSlotGroups(slotOptions, t)
  return (
    <div className={styles.page}>
      <TopBar backTo="/dashboard" backLabel={t('admin.backToDashboard')} />
      <main className={styles.main}>
        {config.current_phase === 'conference'
          ? <SessionReadOnly session={initial} slotGroups={slotGroups} />
          : <SessionForm initial={initial} slotGroups={slotGroups} />
        }
      </main>
    </div>
  )
}

export default function ConsultantSessionPage() {
  const [sessionPromise] = useState(fetchConsultantSession)
  const [slotOptionsPromise] = useState(fetchSlotOptions)
  const [configPromise] = useState(fetchConfig)
  return (
    <Suspense fallback={<div style={{ padding: '2rem', textAlign: 'center' }}>…</div>}>
      <SessionPageContent sessionPromise={sessionPromise} slotOptionsPromise={slotOptionsPromise} configPromise={configPromise} />
    </Suspense>
  )
}
