import client from './client'

export interface DirectoryConsultant {
  id: number
  name: string
  tag: string | null
  gender: 'male' | 'female' | null
  graduation_year: number | null
  profile_picture_url: string | null
}

export async function fetchConsultantDirectory(): Promise<DirectoryConsultant[]> {
  const { data } = await client.get('/consultant-directory')
  return data
}
