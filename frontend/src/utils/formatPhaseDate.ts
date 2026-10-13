export function formatPhaseDate(iso: string, withTime = true): string {
  const d = new Date(iso)
  const date = `${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()}`
  if (!withTime) return date
  const minutes = d.getMinutes().toString().padStart(2, '0')
  return `${date} ${d.getHours()}:${minutes}`
}
