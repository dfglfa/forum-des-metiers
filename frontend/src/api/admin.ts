import client from './client'
import type { User } from './auth'

export interface Tag {
  id: number
  name: string
  slug: string
}

export interface AdminTopic {
  id: number
  title: string
  description: string | null
  consultant_id: number
  tag_id: number
  consultant: Pick<User, 'id' | 'name' | 'email'>
  tag: Tag
  created_at: string
}

export interface AdminConsultantProfile {
  id: number
  first_name: string | null
  last_name: string | null
  phone: string | null
  graduation_year: number | null
  serie: string | null
  language: string | null
  linkedin_url: string | null
  career_path: string | null
  current_situation: string | null
  why_this_career: string | null
  profile_picture_url: string | null
  consent_poster: boolean
  consent_alumni_data: boolean
}

export interface AdminConsultantTopic {
  id: number
  title: string
  description: string | null
  selected_slots: string[]
  tag: Tag | null
}

export interface AdminConsultantDetail {
  id: number
  name: string
  email: string | null
  ldap_username: string | null
  is_participating: boolean
  consultant_profile: AdminConsultantProfile | null
  topics: AdminConsultantTopic[]
}

export async function fetchAdminStudents(): Promise<User[]> {
  const { data } = await client.get('/admin/students')
  return data
}

export interface StudentImportSkippedRow {
  username: string
  reason: string
}

export interface StudentImportResult {
  imported_count: number
  imported: string[]
  skipped: StudentImportSkippedRow[]
}

export async function importStudents(csv: File): Promise<StudentImportResult> {
  const form = new FormData()
  form.append('csv', csv)
  const { data } = await client.post('/admin/students/import', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export interface AdminConsultantListItem extends User {
  topics: AdminConsultantTopic[]
}

export async function fetchAdminConsultants(): Promise<AdminConsultantListItem[]> {
  const { data } = await client.get('/admin/consultants')
  return data
}

export async function fetchAdminConsultantDetail(id: number): Promise<AdminConsultantDetail> {
  const { data } = await client.get(`/admin/consultants/${id}`)
  return data
}

export async function fetchAdminTopics(): Promise<AdminTopic[]> {
  const { data } = await client.get('/admin/topics')
  return data
}

export async function fetchAdminTags(): Promise<Tag[]> {
  const { data } = await client.get('/admin/tags')
  return data
}

export async function createTag(name: string): Promise<Tag> {
  const { data } = await client.post('/admin/tags', { name })
  return data
}

export async function updateTag(id: number, name: string): Promise<Tag> {
  const { data } = await client.put(`/admin/tags/${id}`, { name })
  return data
}

export async function deleteTag(id: number): Promise<void> {
  await client.delete(`/admin/tags/${id}`)
}

export async function updateTopicTag(topicId: number, tagId: number): Promise<AdminConsultantTopic> {
  const { data } = await client.post(`/admin/topics/${topicId}/tag`, { tag_id: tagId })
  return data
}

export async function deleteConsultant(id: number): Promise<void> {
  await client.delete(`/admin/consultants/${id}`)
}

export async function updateConsultantParticipation(id: number, isParticipating: boolean): Promise<User> {
  const { data } = await client.post(`/admin/consultants/${id}/participation`, { is_participating: isParticipating })
  return data
}
