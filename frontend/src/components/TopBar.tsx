import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../contexts/AuthContext'
import AppTitle from './AppTitle'
import LanguageSwitcher from './LanguageSwitcher'
import styles from './TopBar.module.css'

export default function TopBar({ backTo, backLabel }: { backTo?: string; backLabel?: string }) {
  const { t } = useTranslation()
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    navigate('/login')
  }

  return (
    <header className={styles.header}>
      <AppTitle className={styles.appName} />
      <div className={styles.headerRight}>
        {(user?.role === 'consultant' || user?.role === 'student') && !backTo && (
          <Link to="/consultants" className={styles.backBtn}>{t('directory.navLabel')}</Link>
        )}
        {backTo && <Link to={backTo} className={styles.backBtn}>{backLabel}</Link>}
        <LanguageSwitcher />
        <button className={styles.logoutBtn} onClick={handleLogout}>
          {t('dashboard.signOut')}
        </button>
      </div>
    </header>
  )
}
