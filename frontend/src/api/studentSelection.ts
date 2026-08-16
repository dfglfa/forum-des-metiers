import client from './client'

export const MIN_TAG_SELECTIONS = 1
export const MAX_TAG_SELECTIONS = 6

export interface StudentSelectionData {
  tag_ids: number[]
}

export async function fetchStudentSelection(): Promise<StudentSelectionData> {
  const { data } = await client.get('/student/selection')
  return data
}

export async function saveStudentSelection(tagIds: number[]): Promise<StudentSelectionData> {
  const { data } = await client.post('/student/selection', { tag_ids: tagIds })
  return data
}
