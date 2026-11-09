import { Link } from 'react-router-dom'

function DonneesHistoriquesPage() {
  return (
    <section className="donnees-historiques">
      <h1>Données Historiques</h1>
      <p>Bienvenue sur la page des données historiques.</p>
      <Link to="/">Retour à l'accueil</Link>
    </section>
  )
}

export default DonneesHistoriquesPage
