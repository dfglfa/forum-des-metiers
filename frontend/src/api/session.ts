import client from './client'
import type { SlotOption } from './slotOptions'

export interface SlotGroup {
  key: string
  label: string
  slots: { id: string; time: string }[]
}

/**
 * Builds the two selectable slot groups (in-person, reception) from the
 * admin-editable slot option list: every presentation option is offered
 * in-person only (video conference participation is no longer allowed),
 * while reception options are offered once. Groups with no slots (e.g. all
 * reception options removed) are omitted.
 */
export function buildSlotGroups(options: SlotOption[], t: (key: string) => string): SlotGroup[] {
  const presentation = options.filter(o => o.kind === 'presentation')
  const reception = options.filter(o => o.kind === 'reception')
  const timeLabel = (o: SlotOption) => `${o.start_time}–${o.end_time}`

  const groups: SlotGroup[] = [
    {
      key: 'in_person',
      label: t('session.slotGroupInPerson'),
      slots: presentation.map(o => ({ id: `in_person_${o.id}`, time: timeLabel(o) })),
    },
    {
      key: 'reception',
      label: t('session.slotGroupReception'),
      slots: reception.map(o => ({ id: `reception_${o.id}`, time: timeLabel(o) })),
    },
  ]

  return groups.filter(g => g.slots.length > 0)
}

export type SlotId = string

export interface ConsultantSession {
  id: number
  title: string
  description: string | null
  selected_slots: SlotId[]
  tag: { id: number; name: string; slug: string } | null
  time_slots: { id: number; room: string | null }[]
}

export async function fetchConsultantSession(): Promise<ConsultantSession | null> {
  const { data } = await client.get('/consultant/session')
  return data
}

export async function updateConsultantSession(payload: {
  title: string
  description: string | null
  selected_slots: SlotId[]
}): Promise<ConsultantSession> {
  const { data } = await client.post('/consultant/session', payload)
  return data
}
