import client from './client'

export type Phase = 'preparation' | 'selection' | 'conference'

export interface EventTitle {
  de: string
  fr: string
}

export interface GraduationYearRange {
  min: number
  max: number
}

export interface AppConfig {
  ldap_consultants: boolean
  ldap_students: boolean
  current_phase: Phase
  max_tag_choices: number
  assigned_tags_count: number
  event_manager_email: string | null
  event_title: EventTitle
  event_datetime: string | null
  event_location: string | null
  event_logo_url: string | null
  event_favicon_url: string | null
  selection_phase_start: string | null
  conference_phase_start: string | null
  graduation_year_range: GraduationYearRange
}

export async function fetchConfig(): Promise<AppConfig> {
  const { data } = await client.get('/config')
  return data
}

export async function setPhase(phase: Phase): Promise<void> {
  await client.post('/admin/phase', { phase })
}

export async function setLdapStudents(enabled: boolean): Promise<void> {
  await client.post('/admin/ldap-students', { ldap_students: enabled })
}

export async function setEventTitle(eventTitle: EventTitle): Promise<void> {
  await client.post('/admin/event-title', eventTitle)
}

export async function setEventDetails(details: {
  event_datetime: string | null
  event_location: string | null
  event_manager_email: string | null
}): Promise<void> {
  await client.post('/admin/event-details', details)
}

export async function setPhaseDates(dates: { selection_phase_start: string | null; conference_phase_start: string | null }): Promise<void> {
  await client.post('/admin/phase-dates', dates)
}

export async function setGraduationYearRange(range: GraduationYearRange): Promise<void> {
  await client.post('/admin/graduation-year-range', range)
}

export async function setEventLogo(logo: File): Promise<{ event_logo_url: string | null; event_favicon_url: string | null }> {
  const form = new FormData()
  form.append('logo', logo)
  const { data } = await client.post('/admin/event-logo', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function removeEventLogo(): Promise<{ event_logo_url: string | null; event_favicon_url: string | null }> {
  const { data } = await client.delete('/admin/event-logo')
  return data
}
