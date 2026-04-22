const permanentTeeth = {
    upperRight: [1, 2, 3, 4, 5, 6, 7, 8],
    upperLeft: [9, 10, 11, 12, 13, 14, 15, 16],
    lowerLeft: [17, 18, 19, 20, 21, 22, 23, 24],
    lowerRight: [25, 26, 27, 28, 29, 30, 31, 32],
};

const primaryTeeth = {
    upperRight: ['A', 'B', 'C', 'D', 'E'],
    upperLeft: ['F', 'G', 'H', 'I', 'J'],
    lowerLeft: ['K', 'L', 'M', 'N', 'O'],
    lowerRight: ['P', 'Q', 'R', 'S', 'T'],
};

let selectedTeeth = [];

const quadrantLabels = {
    upperRight: 'Upper Right',
    upperLeft: 'Upper Left',
    lowerLeft: 'Lower Left',
    lowerRight: 'Lower Right',
};

function renderToothChart(containerId, type = 'permanent', preselected = []) {
    selectedTeeth = Array.isArray(preselected) ? preselected.map(String) : [];
    const container = document.getElementById(containerId);
    if (!container) return;

    const toothData = type === 'primary' ? primaryTeeth : permanentTeeth;
    container.innerHTML = `
        <div class="tooth-chart ${type}">
            ${Object.entries(toothData)
                .map(([quadrant, teeth]) => `
                    <div class="tooth-quadrant">
                        <div class="quadrant-label">${quadrantLabels[quadrant] || quadrant}</div>
                        <div class="tooth-row">
                            ${teeth
                                .map((tooth) => {
                                    const toothKey = String(tooth);
                                    const isSelected = selectedTeeth.includes(toothKey);
                                    return `
                                        <button type="button" class="tooth-btn ${isSelected ? 'selected' : ''}" data-tooth="${toothKey}">${toothKey}</button>
                                    `;
                                })
                                .join('')}
                        </div>
                    </div>
                `)
                .join('')}
        </div>
    `;

    container.querySelectorAll('.tooth-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const tooth = button.dataset.tooth;
            const index = selectedTeeth.indexOf(tooth);
            if (index === -1) {
                selectedTeeth.push(tooth);
                button.classList.add('selected');
            } else {
                selectedTeeth.splice(index, 1);
                button.classList.remove('selected');
            }
        });
    });
}

function getSelectedTeeth() {
    return [...selectedTeeth];
}

window.renderToothChart = renderToothChart;
window.getSelectedTeeth = getSelectedTeeth;
