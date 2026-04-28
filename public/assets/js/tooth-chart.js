const permanentTeeth = {
    upper: [8, 7, 6, 5, 4, 3, 2, 1, 16, 15, 14, 13, 12, 11, 10, 9],
    lower: [17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32],
};

const primaryTeeth = {
    upper: ['E', 'D', 'C', 'B', 'A', 'J', 'I', 'H', 'G', 'F'],
    lower: ['K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'],
};

const toothNames = {
    // Permanent upper right (1-8)
    1: 'Upper Right Central Incisor',
    2: 'Upper Right Lateral Incisor',
    3: 'Upper Right Canine',
    4: 'Upper Right 1st Premolar',
    5: 'Upper Right 2nd Premolar',
    6: 'Upper Right 1st Molar',
    7: 'Upper Right 2nd Molar',
    8: 'Upper Right 3rd Molar',
    // Permanent upper left (9-16)
    9: 'Upper Left 3rd Molar',
    10: 'Upper Left 2nd Molar',
    11: 'Upper Left 1st Molar',
    12: 'Upper Left 2nd Premolar',
    13: 'Upper Left 1st Premolar',
    14: 'Upper Left Canine',
    15: 'Upper Left Lateral Incisor',
    16: 'Upper Left Central Incisor',
    // Permanent lower left (17-24)
    17: 'Lower Left 3rd Molar',
    18: 'Lower Left 2nd Molar',
    19: 'Lower Left 1st Molar',
    20: 'Lower Left 2nd Premolar',
    21: 'Lower Left 1st Premolar',
    22: 'Lower Left Canine',
    23: 'Lower Left Lateral Incisor',
    24: 'Lower Left Central Incisor',
    // Permanent lower right (25-32)
    25: 'Lower Right Central Incisor',
    26: 'Lower Right Lateral Incisor',
    27: 'Lower Right Canine',
    28: 'Lower Right 1st Premolar',
    29: 'Lower Right 2nd Premolar',
    30: 'Lower Right 1st Molar',
    31: 'Lower Right 2nd Molar',
    32: 'Lower Right 3rd Molar',
    // Primary upper
    A: 'Upper Right Central Incisor',
    B: 'Upper Right Lateral Incisor',
    C: 'Upper Right Canine',
    D: 'Upper Right 1st Molar',
    E: 'Upper Right 2nd Molar',
    F: 'Upper Left 2nd Molar',
    G: 'Upper Left 1st Molar',
    H: 'Upper Left Canine',
    I: 'Upper Left Lateral Incisor',
    J: 'Upper Left Central Incisor',
    // Primary lower
    K: 'Lower Left 2nd Molar',
    L: 'Lower Left 1st Molar',
    M: 'Lower Left Canine',
    N: 'Lower Left Lateral Incisor',
    O: 'Lower Left Central Incisor',
    P: 'Lower Right Central Incisor',
    Q: 'Lower Right Lateral Incisor',
    R: 'Lower Right Canine',
    S: 'Lower Right 1st Molar',
    T: 'Lower Right 2nd Molar',
};

let selectedTeeth = [];

function getToothName(tooth) {
    return toothNames[String(tooth)] || 'Tooth ' + tooth;
}

function computeArchOffset(index, total, archType) {
    const center = (total - 1) / 2;
    const distance = Math.abs(index - center);
    const normalizedDist = distance / center; // 0 at center, 1 at edges

    // Parabolic curve factor
    const curveStrength = archType === 'upper' ? -10 : 10; // upper: ∩, lower: ∪
    const yOffset = Math.pow(normalizedDist, 1.6) * curveStrength;

    // Slight horizontal spacing adjustment for more natural arch
    const xOffset = 0;

    return { x: xOffset, y: yOffset };
}

function renderToothChart(containerId, type = 'permanent', preselected = []) {
    selectedTeeth = Array.isArray(preselected) ? preselected.map(String) : [];
    const container = document.getElementById(containerId);
    if (!container) return;

    const data = type === 'primary' ? primaryTeeth : permanentTeeth;
    const isPermanent = type === 'permanent';
    const toothCount = isPermanent ? 16 : 10;

    // Build upper arch
    let upperHtml = '<div class="arch arch-upper">';
    data.upper.forEach((tooth, i) => {
        const toothKey = String(tooth);
        const isSelected = selectedTeeth.includes(toothKey);
        const offset = computeArchOffset(i, toothCount, 'upper');
        const name = getToothName(tooth);
        upperHtml += `
            <button type="button"
                class="tooth-btn ${isSelected ? 'selected' : ''}"
                data-tooth="${toothKey}"
                title="${name}"
                style="transform: translateY(${offset.y}px)">
                <span class="tooth-num">${toothKey}</span>
                <span class="tooth-dot"></span>
            </button>
        `;
    });
    upperHtml += '</div>';

    // Build midline/mouth gap
    const midlineHtml = `
        <div class="arch-midline">
            <div class="midline-left">Right</div>
            <div class="midline-center"></div>
            <div class="midline-right">Left</div>
        </div>
    `;

    // Build lower arch
    let lowerHtml = '<div class="arch arch-lower">';
    data.lower.forEach((tooth, i) => {
        const toothKey = String(tooth);
        const isSelected = selectedTeeth.includes(toothKey);
        const offset = computeArchOffset(i, toothCount, 'lower');
        const name = getToothName(tooth);
        lowerHtml += `
            <button type="button"
                class="tooth-btn ${isSelected ? 'selected' : ''}"
                data-tooth="${toothKey}"
                title="${name}"
                style="transform: translateY(${offset.y}px)">
                <span class="tooth-num">${toothKey}</span>
                <span class="tooth-dot"></span>
            </button>
        `;
    });
    lowerHtml += '</div>';

    container.innerHTML = `
        <div class="dental-chart ${type}">
            ${upperHtml}
            ${midlineHtml}
            ${lowerHtml}
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

