import { useEffect, useState } from "react";

import "../../styles/Modules/_chronoActualisationAPI.scss";

function ChronoActualisationAPI({ derniereActualisation, prochaineActualisation }) {
    const isValidDate = (value) => {
        return value && !Number.isNaN(new Date(value).getTime());
    };

    const calculateSecondsLeft = () => {
        if (!isValidDate(prochaineActualisation)) {
            return null;
        }

        const difference =
            new Date(prochaineActualisation).getTime() - Date.now();

        return Math.max(0, Math.floor(difference / 1000));
    };

    const [secondsLeft, setSecondsLeft] = useState(calculateSecondsLeft);

    useEffect(() => {
        setSecondsLeft(calculateSecondsLeft());

        if (!isValidDate(prochaineActualisation)) {
            return;
        }

        const interval = setInterval(() => {
            setSecondsLeft((previousSeconds) => {
                if (previousSeconds === null) {
                    return null;
                }

                if (previousSeconds <= 1) {
                    window.location.reload();
                    return 0;
                }

                return previousSeconds - 1;
            });
        }, 1000);

        return () => clearInterval(interval);
    }, [prochaineActualisation]);

    const formatDate = (value) => {
        return isValidDate(value)
            ? new Date(value).toLocaleString("fr-FR")
            : "—";
    };

    const formatCountdown = (totalSeconds) => {
        if (totalSeconds === null) {
            return "—";
        }

        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;

        const pad = (value) => String(value).padStart(2, "0");

        if (hours === 0) {
            return `${minutes} min ${pad(seconds)} s`;
        }

        return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    };

    return (
        <div className="Actualisation">
            <p>
                La page s’actualisera à :{" "}
                <strong className="Actualisation__strong">
                    {new Date(prochaineActualisation).toLocaleTimeString("fr-FR", {
                        hour: "2-digit",
                        minute: "2-digit"
                    }).replace(":", " h ")}
                </strong>{" "}
                (dans <strong className="Actualisation__strong">{formatCountdown(secondsLeft)}</strong>)
            </p>
        </div>
    );
}

export default ChronoActualisationAPI;