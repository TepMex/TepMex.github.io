function getRandomData(dataPoints, discrete) {
    let result = [];
    for (let i = 1; i < dataPoints.length; i++) {
        result.push(...generatePoints(dataPoints[i - 1], dataPoints[i], discrete))
    }

    return result;
}

function generatePoints(p1, p2, discrete) {
    let dLat = (p2[0] - p1[0]) / discrete;
    let dLong = (p2[1] - p1[1]) / discrete;

    let result = [];

    for (let i = 0; i < discrete; i++) {
        let rand = Math.floor(Math.random() * 2)
        result.push([p1[0] + dLat * i * rand, p1[1] + dLong * i * rand])
    }

    return result;
}
var data = [
    [
        37.58757002441395,
        55.74945663730465
    ],
    [
        40.42204268066396,
        56.10719083853699
    ],
    [
        43.47624189941396,
        56.27870514569717
    ],
    [
        44.003585649413935,
        56.27870514569717
    ],
    [
        44.86051924316394,
        56.03344882689801
    ],
    [
        45.49772627441394,
        56.03344882689801
    ],
    [
        47.387374711913935,
        56.14400873617693
    ],
    [
        48.37614424316395,
        55.836110053275064
    ],
    [
        49.10124189941394,
        55.836110053275064
    ]
].map(e => [e[1], e[0]])
ymaps.ready(function () {
    var map = new ymaps.Map('map', {
        center: [55.87338224498493, 43.302416577350435],
        controls: ['zoomControl', 'typeSelector', 'fullscreenControl'],
        zoom: 7
    }),

        gradients = [{
            1.0: 'rgba(128, 255, 0, 0.7)',
            0.7: 'rgba(255, 255, 0, 0.8)',
            0.2: 'rgba(234, 72, 58, 0.9)',
            0.1: 'rgba(162, 36, 25, 1)'
        }, {
            1.0: 'rgba(162, 36, 25, 0.7)',
            0.7: 'rgba(234, 72, 58, 0.8)',
            0.2: 'rgba(255, 255, 0, 0.9)',
            0.1: 'rgba(128, 255, 0, 1)'
        }],
        radiuses = [5, 10, 20, 30],
        opacities = [0.4, 0.6, 0.8, 1];
    ymaps.modules.require(['Heatmap'], function (Heatmap) {
        var heatmap = new Heatmap(getRandomData(data, 100), {
            gradient: gradients[0],
            radius: radiuses[1],
            opacity: opacities[2]
        });
        heatmap.setMap(map);

    });
});