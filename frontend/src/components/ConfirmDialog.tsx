import type { ReactNode } from 'react'
import styles from '../pages/DashboardPage.module.css'

export default function ConfirmDialog({
  title,
  description,
  warning,
  cancelLabel,
  confirmLabel,
  busy = false,
  onCancel,
  onConfirm,
}: {
  title: ReactNode
  description: ReactNode
  warning?: ReactNode
  cancelLabel: string
  confirmLabel: string
  busy?: boolean
  onCancel: () => void
  onConfirm: () => void
}) {
  return (
    <div className={styles.dialogOverlay} onClick={() => !busy && onCancel()}>
      <div className={styles.dialog} onClick={e => e.stopPropagation()}>
        <h3 className={styles.dialogTitle}>{title}</h3>
        <p className={styles.dialogDesc}>{description}</p>
        {warning && <p className={styles.dialogWarning}>{warning}</p>}
        <div className={styles.dialogActions}>
          <button className={styles.dialogCancel} onClick={onCancel} disabled={busy}>
            {cancelLabel}
          </button>
          <button className={styles.dialogConfirm} onClick={onConfirm} disabled={busy}>
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
