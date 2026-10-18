import { Suspense, use, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchConfig, setGraduationYearRange } from '../../api/config'
import type { AppConfig } from '../../api/config'
import { fetchSeries, createSeries, updateSeries, deleteSeries } from '../../api/series'
import type { SeriesOption } from '../../api/series'
import { fetchAdminTags, createTag, updateTag, deleteTag } from '../../api/admin'
import type { Tag } from '../../api/admin'
import { fetchSlotOptions, createSlotOption, updateSlotOption, deleteSlotOption } from '../../api/slotOptions'
import type { SlotOption, SlotKind } from '../../api/slotOptions'
import listStyles from './AdminListPage.module.css'
import formStyles from './InviteSpeakerPage.module.css'
import dashboardStyles from '../DashboardPage.module.css'
import styles from './UsersPage.module.css'
import TopBar from '../../components/TopBar'

function SeriesRow({
  series,
  onUpdate,
  onDelete,
}: {
  series: SeriesOption
  onUpdate: (updated: SeriesOption) => void
  onDelete: (id: number) => void
}) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState(false)
  const [name, setName] = useState(series.name)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSave() {
    const trimmed = name.trim()
    if (!trimmed) return
    setBusy(true)
    setError(null)
    try {
      const updated = await updateSeries(series.id, trimmed)
      onUpdate(updated)
      setEditing(false)
    } catch {
      setError(t('admin.series.errorGeneric'))
    } finally {
      setBusy(false)
    }
  }

  function handleCancel() {
    setName(series.name)
    setError(null)
    setEditing(false)
  }

  if (editing) {
    return (
      <td colSpan={2}>
        <div className={styles.editRow}>
          <input type="text" value={name} onChange={e => setName(e.target.value)} maxLength={50} autoFocus />
          <button className={styles.saveBtn} onClick={handleSave} disabled={busy || !name.trim()}>
            {busy ? '…' : t('admin.series.save')}
          </button>
          <button className={styles.cancelBtn} onClick={handleCancel} disabled={busy}>
            {t('admin.phase.cancel')}
          </button>
        </div>
        {error && <p className={styles.error}>{error}</p>}
      </td>
    )
  }

  return (
    <>
      <td>
        <div className={styles.nameRow}>
          <span>{series.name}</span>
          <button
            type="button"
            className={styles.pencilBtn}
            onClick={() => setEditing(true)}
            aria-label={t('admin.consultantDetail.editTag')}
          >
            ✏️
          </button>
        </div>
      </td>
      <td>
        <button className={styles.deleteBtn} onClick={() => onDelete(series.id)}>
          {t('admin.series.delete')}
        </button>
      </td>
    </>
  )
}

function SeriesManager({ dataPromise }: { dataPromise: Promise<SeriesOption[]> }) {
  const initial = use(dataPromise)
  const { t } = useTranslation()

  const [series, setSeries] = useState(initial)
  const [name, setName] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleAdd(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = name.trim()
    if (!trimmed) return
    setBusy(true)
    setError(null)
    try {
      const created = await createSeries(trimmed)
      setSeries(prev => [...prev, created].sort((a, b) => a.name.localeCompare(b.name)))
      setName('')
    } catch (err: unknown) {
      const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      const msg = anyErr?.response?.data?.errors
        ? Object.values(anyErr.response.data.errors).flat().join(' ')
        : anyErr?.response?.data?.message ?? t('admin.series.errorGeneric')
      setError(msg)
    } finally {
      setBusy(false)
    }
  }

  function handleUpdate(updated: SeriesOption) {
    setSeries(prev => prev.map(s => s.id === updated.id ? updated : s).sort((a, b) => a.name.localeCompare(b.name)))
  }

  async function handleDelete(id: number) {
    const previous = series
    setSeries(prev => prev.filter(s => s.id !== id))
    try {
      await deleteSeries(id)
    } catch {
      setSeries(previous)
    }
  }

  return (
    <>
      <form onSubmit={handleAdd} className={styles.addForm}>
        <input
          type="text"
          value={name}
          onChange={e => setName(e.target.value)}
          placeholder={t('admin.series.fieldName')}
          maxLength={50}
        />
        <button type="submit" disabled={busy || !name.trim()}>
          {t('admin.series.add')}
        </button>
      </form>

      {error && <p className={styles.error}>{error}</p>}

      {series.length === 0 ? (
        <p className={listStyles.empty}>{t('admin.noData')}</p>
      ) : (
        <table className={listStyles.table}>
          <thead>
            <tr>
              <th className={styles.nameCol}>{t('admin.series.fieldName')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {series.map(s => (
              <tr key={s.id}>
                <SeriesRow series={s} onUpdate={handleUpdate} onDelete={handleDelete} />
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  )
}

function TagRow({
  tag,
  onUpdate,
  onDelete,
  extractErrorMessage,
}: {
  tag: Tag
  onUpdate: (updated: Tag) => void
  onDelete: (id: number) => void
  extractErrorMessage: (err: unknown, fallback: string) => string
}) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState(false)
  const [name, setName] = useState(tag.name)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSave() {
    const trimmed = name.trim()
    if (!trimmed) return
    setBusy(true)
    setError(null)
    try {
      onUpdate(await updateTag(tag.id, trimmed))
      setEditing(false)
    } catch (err: unknown) {
      setError(extractErrorMessage(err, t('admin.tags.errorUpdate')))
    } finally {
      setBusy(false)
    }
  }

  function handleCancel() {
    setName(tag.name)
    setError(null)
    setEditing(false)
  }

  if (editing) {
    return (
      <td colSpan={2}>
        <div className={styles.editRow}>
          <input type="text" value={name} onChange={e => setName(e.target.value)} maxLength={100} autoFocus />
          <button className={styles.saveBtn} onClick={handleSave} disabled={busy || !name.trim()}>
            {busy ? '…' : t('admin.series.save')}
          </button>
          <button className={styles.cancelBtn} onClick={handleCancel} disabled={busy}>
            {t('admin.phase.cancel')}
          </button>
        </div>
        {error && <p className={styles.error}>{error}</p>}
      </td>
    )
  }

  return (
    <>
      <td>
        <div className={styles.nameRow}>
          <span>{tag.name}</span>
          <button
            type="button"
            className={styles.pencilBtn}
            onClick={() => setEditing(true)}
            aria-label={t('admin.consultantDetail.editTag')}
          >
            ✏️
          </button>
        </div>
      </td>
      <td>
        <button className={styles.deleteBtn} onClick={() => onDelete(tag.id)}>
          {t('admin.tags.delete')}
        </button>
      </td>
    </>
  )
}

function TagsManager({ dataPromise }: { dataPromise: Promise<Tag[]> }) {
  const initial = use(dataPromise)
  const { t } = useTranslation()

  const [tags, setTags] = useState(initial)
  const [name, setName] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function extractErrorMessage(err: unknown, fallback: string): string {
    const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
    return anyErr?.response?.data?.errors
      ? Object.values(anyErr.response.data.errors).flat().join(' ')
      : anyErr?.response?.data?.message ?? fallback
  }

  async function handleAdd(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = name.trim()
    if (!trimmed) return
    setBusy(true)
    setError(null)
    try {
      const created = await createTag(trimmed)
      setTags(prev => [...prev, created].sort((a, b) => a.name.localeCompare(b.name)))
      setName('')
    } catch (err: unknown) {
      setError(extractErrorMessage(err, t('admin.tags.errorGeneric')))
    } finally {
      setBusy(false)
    }
  }

  function handleUpdate(updated: Tag) {
    setTags(prev => prev.map(tag => tag.id === updated.id ? updated : tag).sort((a, b) => a.name.localeCompare(b.name)))
  }

  async function handleDelete(id: number) {
    setError(null)
    const previous = tags
    setTags(prev => prev.filter(tag => tag.id !== id))
    try {
      await deleteTag(id)
    } catch (err: unknown) {
      setTags(previous)
      setError(extractErrorMessage(err, t('admin.tags.errorDelete')))
    }
  }

  return (
    <>
      <form onSubmit={handleAdd} className={styles.addForm}>
        <input
          type="text"
          value={name}
          onChange={e => setName(e.target.value)}
          placeholder={t('admin.tags.fieldName')}
          maxLength={100}
        />
        <button type="submit" disabled={busy || !name.trim()}>
          {t('admin.tags.add')}
        </button>
      </form>

      {error && <p className={styles.error}>{error}</p>}

      {tags.length === 0 ? (
        <p className={listStyles.empty}>{t('admin.noData')}</p>
      ) : (
        <table className={listStyles.table}>
          <thead>
            <tr>
              <th className={styles.nameCol}>{t('admin.tags.fieldName')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {tags.map(tag => (
              <tr key={tag.id}>
                <TagRow
                  tag={tag}
                  onUpdate={handleUpdate}
                  onDelete={handleDelete}
                  extractErrorMessage={extractErrorMessage}
                />
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  )
}

function SlotOptionRow({
  slotOption,
  onUpdate,
  onDelete,
}: {
  slotOption: SlotOption
  onUpdate: (updated: SlotOption) => void
  onDelete: (id: number) => void
}) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState(false)
  const [kind, setKind] = useState<SlotKind>(slotOption.kind)
  const [startTime, setStartTime] = useState(slotOption.start_time)
  const [endTime, setEndTime] = useState(slotOption.end_time)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSave() {
    setBusy(true)
    setError(null)
    try {
      const updated = await updateSlotOption(slotOption.id, { kind, start_time: startTime, end_time: endTime })
      onUpdate(updated)
      setEditing(false)
    } catch {
      setError(t('admin.slotOptions.errorGeneric'))
    } finally {
      setBusy(false)
    }
  }

  function handleCancel() {
    setKind(slotOption.kind)
    setStartTime(slotOption.start_time)
    setEndTime(slotOption.end_time)
    setError(null)
    setEditing(false)
  }

  if (editing) {
    return (
      <td colSpan={4}>
        <div className={styles.editRow}>
          <select value={kind} onChange={e => setKind(e.target.value as SlotKind)}>
            <option value="presentation">{t('admin.slotOptions.kindPresentation')}</option>
            <option value="reception">{t('admin.slotOptions.kindReception')}</option>
          </select>
          <input type="time" value={startTime} onChange={e => setStartTime(e.target.value)} />
          <input type="time" value={endTime} onChange={e => setEndTime(e.target.value)} />
          <button className={styles.saveBtn} onClick={handleSave} disabled={busy || !startTime || !endTime}>
            {busy ? '…' : t('admin.series.save')}
          </button>
          <button className={styles.cancelBtn} onClick={handleCancel} disabled={busy}>
            {t('admin.phase.cancel')}
          </button>
        </div>
        {error && <p className={styles.error}>{error}</p>}
      </td>
    )
  }

  return (
    <>
      <td>
        <div className={styles.nameRow}>
          <span>{slotOption.kind === 'presentation' ? t('admin.slotOptions.kindPresentation') : t('admin.slotOptions.kindReception')}</span>
          <button
            type="button"
            className={styles.pencilBtn}
            onClick={() => setEditing(true)}
            aria-label={t('admin.consultantDetail.editTag')}
          >
            ✏️
          </button>
        </div>
      </td>
      <td>{slotOption.start_time}</td>
      <td>{slotOption.end_time}</td>
      <td>
        <button className={styles.deleteBtn} onClick={() => onDelete(slotOption.id)}>
          {t('admin.series.delete')}
        </button>
      </td>
    </>
  )
}

function SlotOptionsManager({ dataPromise }: { dataPromise: Promise<SlotOption[]> }) {
  const initial = use(dataPromise)
  const { t } = useTranslation()

  const [slotOptions, setSlotOptions] = useState(initial)
  const [kind, setKind] = useState<SlotKind>('presentation')
  const [startTime, setStartTime] = useState('')
  const [endTime, setEndTime] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function sorted(options: SlotOption[]): SlotOption[] {
    return [...options].sort((a, b) => a.kind.localeCompare(b.kind) || a.start_time.localeCompare(b.start_time))
  }

  function extractErrorMessage(err: unknown, fallback: string): string {
    const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
    return anyErr?.response?.data?.errors
      ? Object.values(anyErr.response.data.errors).flat().join(' ')
      : anyErr?.response?.data?.message ?? fallback
  }

  async function handleAdd(e: React.FormEvent) {
    e.preventDefault()
    if (!startTime || !endTime) return
    setBusy(true)
    setError(null)
    try {
      const created = await createSlotOption({ kind, start_time: startTime, end_time: endTime })
      setSlotOptions(prev => sorted([...prev, created]))
      setStartTime('')
      setEndTime('')
    } catch (err: unknown) {
      setError(extractErrorMessage(err, t('admin.slotOptions.errorGeneric')))
    } finally {
      setBusy(false)
    }
  }

  function handleUpdate(updated: SlotOption) {
    setSlotOptions(prev => sorted(prev.map(s => s.id === updated.id ? updated : s)))
  }

  async function handleDelete(id: number) {
    const previous = slotOptions
    setSlotOptions(prev => prev.filter(s => s.id !== id))
    try {
      await deleteSlotOption(id)
    } catch {
      setSlotOptions(previous)
    }
  }

  return (
    <>
      <form onSubmit={handleAdd} className={styles.slotAddForm}>
        <select value={kind} onChange={e => setKind(e.target.value as SlotKind)}>
          <option value="presentation">{t('admin.slotOptions.kindPresentation')}</option>
          <option value="reception">{t('admin.slotOptions.kindReception')}</option>
        </select>
        <input type="time" value={startTime} onChange={e => setStartTime(e.target.value)} required />
        <input type="time" value={endTime} onChange={e => setEndTime(e.target.value)} required />
        <button type="submit" disabled={busy || !startTime || !endTime}>
          {t('admin.slotOptions.add')}
        </button>
      </form>

      {error && <p className={styles.error}>{error}</p>}

      {slotOptions.length === 0 ? (
        <p className={listStyles.empty}>{t('admin.noData')}</p>
      ) : (
        <table className={listStyles.table}>
          <thead>
            <tr>
              <th>{t('admin.slotOptions.fieldKind')}</th>
              <th>{t('admin.slotOptions.fieldStart')}</th>
              <th>{t('admin.slotOptions.fieldEnd')}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {slotOptions.map(s => (
              <tr key={s.id}>
                <SlotOptionRow slotOption={s} onUpdate={handleUpdate} onDelete={handleDelete} />
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  )
}

function GraduationYearRangeForm({ config }: { config: AppConfig }) {
  const { t } = useTranslation()
  const latestAllowedYear = new Date().getFullYear()

  const [min, setMin] = useState(String(config.graduation_year_range.min))
  const [max, setMax] = useState(String(config.graduation_year_range.max))
  const [busy, setBusy] = useState(false)
  const [success, setSuccess] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setBusy(true)
    setSuccess(false)
    setError(null)
    try {
      await setGraduationYearRange({ min: Number(min), max: Number(max) })
      setSuccess(true)
    } catch (err: unknown) {
      const anyErr = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      const msg = anyErr?.response?.data?.errors
        ? Object.values(anyErr.response.data.errors).flat().join(' ')
        : anyErr?.response?.data?.message ?? t('admin.graduationYearRange.errorGeneric')
      setError(msg)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className={formStyles.formCard}>
      <form onSubmit={handleSubmit} className={formStyles.form}>
        <div className={formStyles.row}>
          <label className={formStyles.field}>
            <span>{t('admin.graduationYearRange.fieldMin')}</span>
            <input type="number" value={min} onChange={e => setMin(e.target.value)} min={1900} max={latestAllowedYear} required />
          </label>
          <label className={formStyles.field}>
            <span>{t('admin.graduationYearRange.fieldMax')}</span>
            <input type="number" value={max} onChange={e => setMax(e.target.value)} min={1900} max={latestAllowedYear} required />
          </label>
        </div>

        {error && <p className={formStyles.error}>{error}</p>}
        {success && <p className={formStyles.success}>{t('admin.graduationYearRange.success')}</p>}

        <button type="submit" className={formStyles.submit} disabled={busy}>
          {busy ? t('admin.graduationYearRange.submitting') : t('admin.graduationYearRange.submit')}
        </button>
      </form>
    </div>
  )
}

function UsersPageContent({
  configPromise,
  seriesPromise,
  tagsPromise,
  slotOptionsPromise,
}: {
  configPromise: Promise<AppConfig>
  seriesPromise: Promise<SeriesOption[]>
  tagsPromise: Promise<Tag[]>
  slotOptionsPromise: Promise<SlotOption[]>
}) {
  const config = use(configPromise)
  const { t } = useTranslation()

  return (
    <>
      <span className={dashboardStyles.phaseLabel}>{t('admin.tagsOverview')}</span>
      <TagsManager dataPromise={tagsPromise} />

      <hr className={dashboardStyles.phaseDivider} />
      <span className={dashboardStyles.phaseLabel}>{t('admin.graduationYearRange.title')}</span>
      <GraduationYearRangeForm config={config} />

      <hr className={dashboardStyles.phaseDivider} />
      <span className={dashboardStyles.phaseLabel}>{t('admin.seriesOverview')}</span>
      <SeriesManager dataPromise={seriesPromise} />

      <hr className={dashboardStyles.phaseDivider} />
      <span className={dashboardStyles.phaseLabel}>{t('admin.slotOptions.overview')}</span>
      <SlotOptionsManager dataPromise={slotOptionsPromise} />
    </>
  )
}

export default function UsersPage() {
  const { t } = useTranslation()
  const [configPromise] = useState(() => fetchConfig())
  const [seriesPromise] = useState(() => fetchSeries())
  const [tagsPromise] = useState(() => fetchAdminTags())
  const [slotOptionsPromise] = useState(() => fetchSlotOptions())

  return (
    <div className={listStyles.page}>
      <TopBar backTo="/dashboard" backLabel={t('admin.backToDashboard')} />
      <main className={listStyles.main}>
        <h1 className={listStyles.title}>{t('admin.settingsOverview')}</h1>
        <Suspense fallback={<p className={listStyles.empty}>…</p>}>
          <UsersPageContent
            configPromise={configPromise}
            seriesPromise={seriesPromise}
            tagsPromise={tagsPromise}
            slotOptionsPromise={slotOptionsPromise}
          />
        </Suspense>
      </main>
    </div>
  )
}
