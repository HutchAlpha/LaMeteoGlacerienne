import { useEffect } from 'react'
import { NavLink, Route, Routes } from 'react-router-dom'
import { useSelector } from 'react-redux'

import Erreur404 from './pages/Erreur/Erreur404.jsx';

import IndexPage from './pages/Index.jsx';
import DonneesHistoriquesPage from './pages/donnees-historiques.jsx';

function App() {
  const theme = useSelector((state) => state.preferences.theme)

  useEffect(() => {
    document.documentElement.dataset.theme = theme
  }, [theme])

  return (
    <div className="App">
      <nav>
        <NavLink to="/">Accueil</NavLink>
        <NavLink to="/donnees-historiques">Voir les données historiques</NavLink>
      </nav>

      <Routes>
          <Route path="/" element={<IndexPage />} />
          <Route path="/donnees-historiques" element={<DonneesHistoriquesPage />} />
          <Route path="*" element={<Erreur404 />} />
      </Routes>
    </div>
  )
}

export default App
