import { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { Link } from 'react-router-dom'
import { chargerHistoriqueMeteo } from '../store/slices/meteoSlice.js'

function DonneesHistoriquesPage() {
  const dispatch = useDispatch()
  const {
    historique,
    historiqueTotal,
    historiqueStatus,
    historiqueErreur,
  } = useSelector((state) => state.meteo)

  useEffect(() => {
    dispatch(chargerHistoriqueMeteo())
  }, [dispatch])

  return (
    <section className="donnees-historiques">
      <h1>Historique météo</h1>
      <p>Relevés passés enregistrés dans la base de données</p>
      {historiqueStatus === 'loading' && (
        <p role="status">Chargement de l’historique depuis la base de données…</p>
      )}
      {historiqueStatus === 'failed' && <p role="alert">{historiqueErreur}</p>}
      {historiqueStatus === 'succeeded' && historique.length === 0 && (
        <p>La base de données ne contient encore aucun relevé.</p>
      )}
      {historiqueStatus === 'succeeded' && historique.length > 0 && (
        <>
          <p>{historiqueTotal} relevé(s) au total :</p>
          <div className="donnees-historiques__div">
            <table className="donnees-historiques__table">
              <thead>
                <tr>
                  <th scope="col">Date</th>
                  <th scope="col">Température</th>
                  <th scope="col">Min.</th>
                  <th scope="col">Max.</th>
                  <th scope="col">Ressenti</th>
                  <th scope="col">Humidité</th>
                  <th scope="col">Pluie</th>
                  <th scope="col">Neige</th>
                  <th scope="col">Vent</th>
                  <th scope="col">Rafales</th>
                  <th scope="col">Pression</th>
                  <th scope="col">Conditions</th>
                </tr>
              </thead>
              <tbody>
                {historique.map((releve) => (
                  <tr key={releve.id}>
                    <td>{releve.date ?? '—'}</td>
                    <td>{releve.temperature == null ? '—' : `${releve.temperature} °C`}</td>
                    <td>{releve.temp_min == null ? '—' : `${releve.temp_min} °C`}</td>
                    <td>{releve.temp_max == null ? '—' : `${releve.temp_max} °C`}</td>
                    <td>{releve.ressenti == null ? '—' : `${releve.ressenti} °C`}</td>
                    <td>{releve.humidite == null ? '—' : `${releve.humidite} %`}</td>
                    <td>{releve.precipitation == null ? '—' : `${releve.precipitation} mm`}</td>
                    <td>{releve.neige == null ? '—' : `${releve.neige} cm`}</td>
                    <td>{releve.vitesse_vent == null ? '—' : `${releve.vitesse_vent} km/h`}</td>
                    <td>{releve.rafales == null ? '—' : `${releve.rafales} km/h`}</td>
                    <td>{releve.pression == null ? '—' : `${releve.pression} hPa`}</td>
                    <td>
                      {releve.icone && (
                        <img
                          className="donnees-historiques__weatherIcon"
                          src={`https://openweathermap.org/img/wn/${releve.icone}.png`}
                          alt=""
                        />
                      )}
                      {releve.description ?? '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
      <Link to="/">Retour à l'accueil</Link>
    </section>
  )
}

export default DonneesHistoriquesPage
