import client from './client'

export interface StudentTagTopic {
  id: number
  title: string
  consultant: { id: number; name: string } | null
}

export interface StudentTag {
  id: number
  name: string
  slug: string
  description: string | null
  topics_count: number
  topics: StudentTagTopic[]
}

export async function fetchStudentTags(): Promise<StudentTag[]> {
  const { data } = await client.get('/student/tags')
  return data
}
