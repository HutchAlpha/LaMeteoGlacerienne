import { useEffect, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import { Link } from "react-router-dom";
import { chargerDonneesMeteo } from "../store/slices/meteoSlice.js";

import ChronoActualisationAPI from "./Modules/ChronoActualisationAPI.jsx";

import "../styles/_index.scss";

export default function IndexPage() {
  const dispatch = useDispatch()
  const { ville, donnees, status, erreur, prochaineActualisation } = useSelector((state) => state.meteo)
  
  const [previsionOuverte, setPrevisionOuverte] = useState(null);
  
  useEffect(() => {
    dispatch(chargerDonneesMeteo('La Glacerie'))
  }, [dispatch])
  
  //! Fermer automatiquement la prévision ouverte après 4 secondes
  useEffect(() => {
    if (previsionOuverte === null) {
        return;
    }

    const timeoutId = setTimeout(() => {
        setPrevisionOuverte(null);
    }, 4000);

    return () => {
        clearTimeout(timeoutId);
    };
}, [previsionOuverte]);

//! Gestion de la séparation des jours
const previsionsDuJour = [...donnees].sort((a, b) => {
    return new Date(a.date).getTime() - new Date(b.date).getTime();
});

const previsionsParJour = previsionsDuJour.reduce((groupes, prevision) => {
    const jour = new Date(prevision.date).toLocaleDateString('fr-FR', {
        timeZone: 'Europe/Paris'
    });

    if (!groupes[jour]) {
        groupes[jour] = [];
    }

    groupes[jour].push(prevision);

    return groupes;
}, {});

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


          <div className="index__divPrevisions">

          <ChronoActualisationAPI
            derniereActualisation={donnees[0]?.date}
            prochaineActualisation={prochaineActualisation}
          />


          {Object.entries(previsionsParJour).map(([jour, previsionsDuJour]) => (
            <div className="index__jour" key={jour}>
            <h3>Prévisions du {jour}</h3>

          <div className="index__tableWrapper">
            <table className="index__table">
              <thead>
                <tr className="index__tr">
                  <th scope="col">Date et heure</th>
                    <th scope="col">Conditions</th>
                    <th scope="col">Température</th>
                    <th scope="col">Ressenti</th>
                    <th scope="col">Humidité</th>
                    <th scope="col">Vent</th>
                    <th scope="col">Rafales</th>
                    <th scope="col">Pluie (3 h)</th>
                    <th scope="col">Neige (3 h)</th>
                    <th scope="col">Pression</th>
                  </tr>
                </thead>
                <tbody>
                  {previsionsDuJour.map((prevision) => (
                    <tr className="index__tr" key={prevision.date}>
                      <td>{new Date(prevision.date).toLocaleString('fr-FR')}</td>
                      <td>
                          {prevision.weather_icon && (
                          <button
                            className="index__IconMeteoLink"
                            type="button"
                            aria-expanded={previsionOuverte === prevision.date}
                            onClick={() => {
                                setPrevisionOuverte((dateActuelle) =>
                                    dateActuelle === prevision.date
                                        ? null
                                        : prevision.date
                                );
                            }}
                          >
                            <img
                              className="index__weatherIcon"
                              src={`https://openweathermap.org/img/wn/${prevision.weather_icon}.png`}
                              alt={`Détails météo : ${prevision.description ?? 'conditions indisponibles'}`}
                            />
                          </button>
                          )}

                          {(previsionOuverte === prevision.date || !prevision.weather_icon) && (
                              <p className="index__weatherDescription">
                                  {prevision.description ?? '—'}
                              </p>
                          )}
                        </td>
                      <td>{prevision.temperature} °C</td>
                      <td>{prevision.feels_like == null ? '—' : `${prevision.feels_like} °C`}</td>
                      <td>{prevision.humidite} %</td>
                      <td>{prevision.vent == null ? '—' : `${prevision.vent.toFixed(1)} km/h`}</td>
                      <td>{prevision.wind_gust == null ? '—' : `${prevision.wind_gust.toFixed(1)} km/h`}</td>
                      <td>{prevision.precipitation} mm</td>
                      <td>{prevision.snow} cm</td>
                      <td>{prevision.pressure == null ? '—' : `${prevision.pressure} hPa`}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            </div>
            ))}
          </div>
        </>
      )}
      <Link to="/donnees-historiques">Consulter les relevés historiques →</Link>
    </section>
  )
}
