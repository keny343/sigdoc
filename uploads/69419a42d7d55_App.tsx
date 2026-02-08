import React, { useState } from 'react';
import { Navbar } from './components/Navbar';
import { Hero } from './components/Hero';
import { Features } from './components/Features';
import { CTASection } from './components/CTASection';
import { Footer } from './components/Footer';

type Language = 'pt' | 'en';

export default function App() {
  const [lang, setLang] = useState<Language>('pt');

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-slate-100">
      <Navbar lang={lang} setLang={setLang} />
      <Hero lang={lang} />
      <Features lang={lang} />
      <CTASection lang={lang} />
      <Footer lang={lang} />
    </div>
  );
}
