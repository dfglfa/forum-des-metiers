import client from './client'

export interface DirectoryConsultant {
  id: number
  name: string
  graduation_year: number | null
  serie: string | null
  career_path: string | null
  current_situation: string | null
  why_this_career: string | null
  profile_picture_url: string | null
}

export async function fetchConsultantDirectory(): Promise<DirectoryConsultant[]> {
  const { data } = await client.get('/consultant-directory')
  return data
}
