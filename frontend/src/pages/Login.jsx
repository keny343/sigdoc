import { useEffect } from 'react'

/** Redirect immediately to PHP login (no intermediate React route). */
export default function Login() {
  useEffect(() => {
    window.location.replace('/auth/login.php')
  }, [])

  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: 'var(--color-bg, #0f172a)',
      color: 'var(--color-text, #e2e8f0)',
    }}>
      <p>A redirecionar para o login… <a href="/auth/login.php">Entrar</a></p>
    </div>
  )
}
