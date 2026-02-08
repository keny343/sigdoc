import { useEffect } from 'react'

export default function Login() {
  useEffect(() => {
    window.location.href = window.location.origin + '/auth/login.php'
  }, [])

  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: 'var(--color-bg)',
      color: 'var(--color-text)',
    }}>
      <p>Redirecionando para o login...</p>
    </div>
  )
}
