import { Link } from 'react-router-dom'

export default function IndexPage() {
    return (
        <section className="index">
        <h1>Bienvenue sur notre site</h1>
        <p>Naviguez entre les différentes pages pour consulter les informations.</p>
        <Link to="/donnees-historiques">Voir les données historiques</Link>
        </section>
    )
}
