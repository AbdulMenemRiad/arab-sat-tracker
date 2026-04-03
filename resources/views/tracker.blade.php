<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arab-Sat Orbital Tracker</title>

    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #050505;
            color: #e0e0e0;
            font-family: 'Share Tech Mono', monospace;
            overflow: hidden;
        }

        /* Glassmorphism UI Panels */
        .ui-panel {
            position: absolute;
            background: rgba(10, 15, 25, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(0, 255, 255, 0.2);
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.05);
            padding: 20px;
            z-index: 10;
        }

        #dashboard {
            top: 20px;
            left: 20px;
            width: 320px;
            max-height: 80vh;
            overflow-y: auto;
        }

        #controls {
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            text-align: center;
        }

        h1 {
            margin: 0 0 5px 0;
            font-size: 1.4rem;
            color: #00ffff;
            text-shadow: 0 0 8px rgba(0, 255, 255, 0.5);
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 15px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }

        .clock {
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #00ff00;
            border-radius: 50%;
            box-shadow: 0 0 8px #00ff00;
            margin-right: 5px;
            animation: blink 2s infinite;
        }

        /* Satellite List Styling */
        .sat-item {
            margin-bottom: 12px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            border-left: 3px solid #00ffff;
            border-radius: 4px;
        }

        .sat-name {
            font-size: 1.1rem;
            color: #00ffff;
            margin-bottom: 4px;
        }

        .sat-data {
            font-size: 0.85rem;
            color: #aaa;
            line-height: 1.4;
        }

        .data-label {
            color: #555;
        }

        /* Time Slider Styling */
        input[type=range] {
            width: 100%;
            margin: 15px 0;
            accent-color: #00ffff;
            cursor: pointer;
        }

        .slider-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #888;
        }

        #globeViz {
            width: 100vw;
            height: 100vh;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }

        @keyframes blink {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.3;
            }

            100% {
                opacity: 1;
            }
        }

        /* Custom Scrollbar for the dashboard */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(0, 255, 255, 0.3);
            border-radius: 3px;
        }
    </style>

<script src="https://unpkg.com/three@0.144.0/build/three.min.js"></script>
    <script src="https://unpkg.com/globe.gl@2.27.2/dist/globe.gl.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/satellite.js/4.1.3/satellite.min.js"></script>
</head>

<body>

    <div id="dashboard" class="ui-panel">
        <h1>MENA Orbital Command</h1>
        <div class="subtitle">Regional Satellite Telemetry</div>
        <div class="clock"><span class="status-dot"></span> <span id="time-display">Loading Time...</span></div>
        <div id="sat-info">Initializing orbital calculations...</div>
    </div>

    <div id="controls" class="ui-panel">
        <div style="color: #00ffff; font-size: 1.1rem; margin-bottom: 5px;">Trajectory Projection Simulator</div>
        <div style="font-size: 0.85rem; color: #aaa;">Slide to project orbital paths into the future</div>

        <input type="range" id="timeSlider" min="0" max="720" value="0">

        <div class="slider-labels">
            <span>LIVE (Real-Time)</span>
            <span>+<span id="slider-val">0</span> Hours</span>
            <span>+12 Hours</span>
        </div>
    </div>

    <div id="globeViz"></div>

    <script>
      // 1. Initialize Global Variables
        let rawSatelliteData = {};
        let timeOffsetMinutes = 0;
        let pathsCalculated = false; // NEW: Performance flag to stop the looping animation

        // 2. Setup the 3D Globe (Removed the transition duration lines)
        const world = Globe()
            (document.getElementById('globeViz'))
            .globeImageUrl('//unpkg.com/three-globe/example/img/earth-night.jpg')
            .backgroundImageUrl('//unpkg.com/three-globe/example/img/night-sky.png')
            .pointOfView({ lat: 25.2, lng: 51.5, altitude: 2.2 })
            .pointAltitude('alt')
            .pointColor(() => '#00ffff')
            .pointRadius(0.15)
            .pointLabel('name')
            .pathColor(() => 'rgba(0, 255, 255, 0.25)')
            .pathPoints('coords')
            .pathPointLat(p => p[0])
            .pathPointLng(p => p[1])
            .pathPointAlt(p => p[2])
            .pathStroke(1.5)
            .width(window.innerWidth)
            .height(window.innerHeight);

        // Make it responsive
        window.addEventListener('resize', () => {
            world.width(window.innerWidth).height(window.innerHeight);
        });

        world.controls().autoRotate = true;
        world.controls().autoRotateSpeed = 0.5;

        // 3. Handle Slider Input
        document.getElementById('timeSlider').addEventListener('input', function(e) {
            timeOffsetMinutes = parseInt(e.target.value);
            document.getElementById('slider-val').innerText = (timeOffsetMinutes / 60).toFixed(1);

            // NEW: If the user moves the slider, we MUST recalculate the paths
            pathsCalculated = false;
            renderScene();
        });

        // 4. The Core Physics Loop
        function renderScene() {
            if (Object.keys(rawSatelliteData).length === 0) return;

            const pointsData = [];
            const pathsData = [];
            let infoHtml = '';

            const realNow = new Date();
            const simTime = new Date(realNow.getTime() + (timeOffsetMinutes * 60000));

            document.getElementById('time-display').innerText = simTime.toISOString().replace('T', ' ').substring(0, 19) + ' UTC';

            for (const [key, sat] of Object.entries(rawSatelliteData)) {
                const satrec = satellite.twoline2satrec(sat.tle_line_1, sat.tle_line_2);

                // --- 4A. CALCULATE DOTS (Runs every second) ---
                const positionAndVelocity = satellite.propagate(satrec, simTime);
                if (positionAndVelocity.position) {
                    const gmst = satellite.gstime(simTime);
                    const positionGd = satellite.eciToGeodetic(positionAndVelocity.position, gmst);

                    const currentLng = satellite.degreesLong(positionGd.longitude);
                    const currentLat = satellite.degreesLat(positionGd.latitude);
                    const currentAlt = positionGd.height / 6371;

                    pointsData.push({ name: key, lat: currentLat, lng: currentLng, alt: currentAlt });

                    infoHtml += `
                        <div class="sat-item">
                            <div class="sat-name">${key}</div>
                            <div class="sat-data">
                                <span class="data-label">ALT:</span> ${Math.round(positionGd.height).toLocaleString()} km <br>
                                <span class="data-label">LAT:</span> ${currentLat.toFixed(4)}&deg; <br>
                                <span class="data-label">LNG:</span> ${currentLng.toFixed(4)}&deg;
                            </div>
                        </div>`;
                }

                // --- 4B. CALCULATE PATHS (Runs ONLY when needed) ---
                if (!pathsCalculated) {
                    const pathCoordinates = [];
                    for (let i = 0; i < 90; i++) {
                        const futureTime = new Date(simTime.getTime() + i * 60000);
                        const futurePos = satellite.propagate(satrec, futureTime);

                        if (futurePos.position) {
                            const futureGmst = satellite.gstime(futureTime);
                            const futureGd = satellite.eciToGeodetic(futurePos.position, futureGmst);
                            pathCoordinates.push([
                                satellite.degreesLat(futureGd.latitude),
                                satellite.degreesLong(futureGd.longitude),
                                futureGd.height / 6371
                            ]);
                        }
                    }
                    pathsData.push({ name: key, coords: pathCoordinates });
                }
            }

            // Always update the dots and the side panel
            world.pointsData(pointsData);
            document.getElementById('sat-info').innerHTML = infoHtml;

            // Only send new paths to the globe if we actually calculated them
            if (!pathsCalculated) {
                world.pathsData(pathsData);
                pathsCalculated = true; // Lock the flag so it doesn't redraw next second!
            }
        }

        // 5. Initial Data Fetch
        fetch('/api/telemetry')
            .then(res => res.json())
            .then(response => {
                rawSatelliteData = response.data;
                renderScene();
                setInterval(renderScene, 1000);
            })
            .catch(err => {
                console.error("API Fetch Error:", err);
                document.getElementById('sat-info').innerHTML = '<div style="color:red">Failed to establish connection with telemetry server.</div>';
            });
    </script>
</body>

</html>
