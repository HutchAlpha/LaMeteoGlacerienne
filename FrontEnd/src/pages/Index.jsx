import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { Link } from "react-router-dom";
import { chargerDonneesMeteo } from "../store/slices/meteoSlice.js";

import ChronoActualisationAPI from "./Modules/ChronoActualisationAPI.jsx";

import "../styles/_index.scss";

export default function IndexPage() {
  const dispatch = useDispatch()
  const { ville, donnees, status, erreur, prochaineActualisation } = useSelector((state) => state.meteo)

  useEffect(() => {
    dispatch(chargerDonneesMeteo('La Glacerie'))
  }, [dispatch])

  return (
    <section className="index">
      <h1 className="index__h1">L’horizon Glacérienne</h1>
      <p className="index__p">(La Glacerie 50470)</p>
      <h2>Prévisions météo</h2>
      {status === 'loading' && <p role="status">Chargement des prévisions…</p>}
      {status === 'failed' && <p role="alert">{erreur}</p>}
      {status === 'succeeded' && donnees.length === 0 && (
        <p>Aucune prévision n’a été renvoyée pour {ville} !</p>
      )}
      {status === 'succeeded' && donnees.length > 0 && (
        <>
          <p>Prévisions des prochains jours pour {ville} :</p>

        <ChronoActualisationAPI
          derniereActualisation={donnees[0]?.date}
          prochaineActualisation={prochaineActualisation}
        />

          <div className="index__divPrevisions">
            <table className="index__table">
              <thead>
                <tr className="index__tr">
                  <th scope="col">Date et heure</th>
                  <th scope="col">Température</th>
                  <th scope="col">Humidité</th>
                  <th scope="col">Précipitations (3 h)</th>
                  <th scope="col">Vent</th>
                  <th scope="col">Conditions</th>
                </tr>
              </thead>
              <tbody>
                {donnees.map((prevision) => (
                  <tr className="index__tr" key={prevision.date}>
                    <td>{new Date(prevision.date).toLocaleString('fr-FR')}</td>
                    <td>{prevision.temperature} °C</td>
                    <td>{prevision.humidite} %</td>
                    <td>{prevision.precipitations} mm</td>
                    <td>{prevision.vent == null ? '—' : `${prevision.vent.toFixed(1)} km/h`}</td>
                    <td>{prevision.description ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
      <Link to="/donnees-historiques">Consulter les relevés historiques →</Link>
    </section>
  )
}
