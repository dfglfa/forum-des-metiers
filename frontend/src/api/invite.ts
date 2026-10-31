import client from './client'
import type { User } from './auth'

export const SALUTATION_OPTIONS = [
  'Herr',
  'Frau',
  '(ohne)',
  'Herr Dr.',
  'Frau Dr.',
  'Dr.',
  'Herr Prof. Dr.',
  'Frau Prof. Dr.',
  'Prof. Dr.',
] as const

export const LANGUAGE_OPTIONS = ['de', 'fr'] as const

export interface InvitePayload {
  salutation: string
  first_name: string
  last_name: string
  email: string
  language: string
  invitation_body: string
}

export interface InviteResult {
  message?: string
  warning?: string
}

export async function inviteSpeaker(payload: InvitePayload): Promise<InviteResult> {
  const { data } = await client.post('/admin/invite', payload)
  return data
}

export interface BulkInviteSkippedRow {
  email: string
  reason: string
}

export interface BulkInviteResult {
  invited_count: number
  invited: string[]
  skipped: BulkInviteSkippedRow[]
}

export async function bulkInviteSpeakers(csv: File, invitationBodyDe: string, invitationBodyFr: string): Promise<BulkInviteResult> {
  const form = new FormData()
  form.append('csv', csv)
  form.append('invitation_body_de', invitationBodyDe)
  form.append('invitation_body_fr', invitationBodyFr)
  const { data } = await client.post('/admin/invite/bulk', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export interface SpeakerMailPayload {
  subject_de: string
  subject_fr: string
  body_de: string
  body_fr: string
}

export async function sendSpeakerMail(payload: SpeakerMailPayload): Promise<{ sent_count: number }> {
  const { data } = await client.post('/admin/speaker-mail', payload)
  return data
}

export async function acceptInvitation(payload: {
  email: string
  token: string
  password: string
  password_confirmation: string
}): Promise<{ token: string; user: User }> {
  const { data } = await client.post('/auth/invitation/accept', payload)
  return data
}
