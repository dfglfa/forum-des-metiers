export interface FormHandle {
  /** Saves the form; resolves to an error message, or null on success. */
  save: () => Promise<string | null>
}
