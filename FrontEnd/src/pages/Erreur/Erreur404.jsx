import { Link } from 'react-router-dom'

function Erreur404() {
  return (
    <section className="erreur-404">
      <h1>Erreur 404</h1>
      <p>La page que vous recherchez n'existe pas.</p>
      <Link to="/">Retour à l'accueil</Link>
    </section>
  )
}

export default Erreur404
