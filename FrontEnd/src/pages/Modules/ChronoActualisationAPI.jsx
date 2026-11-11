import { useEffect, useRef, useState } from "react";
import "../../styles/Modules/_chronoActualisationAPI.scss";

export default function ChronoActualisationAPI({
    prochaineActualisation,
    onActualiser
}) {
    const [maintenant, setMaintenant] = useState(Date.now);
    const echeanceTraitee = useRef(null);

    const horodatage =
        prochaineActualisation == null
            ? NaN
            : new Date(prochaineActualisation).getTime();

    const dateValide = Number.isFinite(horodatage);

    useEffect(() => {
        const interval = setInterval(() => {
            setMaintenant(Date.now());
        }, 1000);

        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        if (!dateValide || maintenant < horodatage) {
            return;
        }

        // Une seule demande d'actualisation par échéance.
        if (echeanceTraitee.current === horodatage) {
            return;
        }

        echeanceTraitee.current = horodatage;
        onActualiser?.();
    }, [dateValide, horodatage, maintenant, onActualiser]);

    const secondesRestantes = dateValide
        ? Math.max(0, Math.ceil((horodatage - maintenant) / 1000))
        : null;

    const formatCountdown = (totalSecondes) => {
        if (totalSecondes === null) {
            return "—";
        }

        const heures = Math.floor(totalSecondes / 3600);
        const minutes = Math.floor((totalSecondes % 3600) / 60);
        const secondes = totalSecondes % 60;

        const pad = (valeur) => String(valeur).padStart(2, "0");

        return `${pad(heures)}:${pad(minutes)}:${pad(secondes)}`;
    };

    const heureActualisation = dateValide
        ? new Date(horodatage)
              .toLocaleTimeString("fr-FR", {
                  timeZone: "Europe/Paris",
                  hour: "2-digit",
                  minute: "2-digit"
              })
              .replace(":", " h ")
        : "—";

    return (
        <>
        <h2>Prévisions par tranches de 3 heures</h2>
        <div className="Actualisation">
            <p className="Actualisation__p">
                La page s’actualisera à :{" "}
                <strong className="Actualisation__strong">{heureActualisation}</strong>{" "}
                (dans{" "}
                <strong className="Actualisation__strong">{formatCountdown(secondesRestantes)}</strong>)
            </p>

            {dateValide && secondesRestantes === 0 && (
                <p>Actualisation demandée. En attente de la prochaine échéance !</p>
            )}
        </div>
        </>
    );
}