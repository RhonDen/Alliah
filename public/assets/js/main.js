document.addEventListener('DOMContentLoaded', () => {
    const bookingForms = document.querySelectorAll('form[data-availability-url]');

    bookingForms.forEach((form) => {
        const dateInput = form.querySelector('input[name="date"]');
        const timeSelect = form.querySelector('select[name="time"]');
        const endpoint = form.dataset.availabilityUrl;

        if (!dateInput || !timeSelect || !endpoint) {
            return;
        }

        const renderPlaceholder = (label, disabled = true) => {
            timeSelect.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = label;
            option.disabled = disabled;
            option.selected = true;
            timeSelect.appendChild(option);
        };

        const populateTimeSlots = (slots, selectedTime) => {
            if (!slots.length) {
                renderPlaceholder('No available slots for this date');
                return;
            }

            timeSelect.innerHTML = '';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select time';
            defaultOption.selected = selectedTime === '';
            timeSelect.appendChild(defaultOption);

            slots.forEach((slot) => {
                const option = document.createElement('option');
                option.value = slot.value;
                option.textContent = slot.label;
                option.selected = slot.value === selectedTime;
                timeSelect.appendChild(option);
            });
        };

        const loadAvailability = async (selectedTime = '') => {
            if (!dateInput.value) {
                renderPlaceholder('Choose a date first');
                return;
            }

            renderPlaceholder('Loading available slots...');

            try {
                const response = await fetch(`${endpoint}?date=${encodeURIComponent(dateInput.value)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to load appointment times.');
                }

                populateTimeSlots(data.slots || [], selectedTime);
            } catch (error) {
                renderPlaceholder(error.message || 'Unable to load appointment times.');
            }
        };

        dateInput.addEventListener('change', () => {
            timeSelect.dataset.selectedTime = '';
            loadAvailability();
        });

        loadAvailability(timeSelect.dataset.selectedTime || timeSelect.value || '');
    });
});

// Patient search for walk-in page
if (document.getElementById('patient_search')) {
    const patientInput = document.getElementById('patient_search');
    const patientList = document.getElementById('patient-search-list');
    const hiddenInput = document.getElementById('user_id');
    const items = patientList?.querySelectorAll('.patient-search-item');
    
    if (!patientInput || !patientList || !hiddenInput) return;

    const filterPatients = () => {
        const query = patientInput.value.toLowerCase().trim();
        let hasMatches = false;

        items.forEach(item => {
            const name = item.dataset.name || '';
            const matches = name.includes(query);
            item.style.display = matches ? 'flex' : 'none';
            if (matches) hasMatches = true;
        });

        patientList.classList.toggle('show', query.length > 0 && hasMatches);
    };

    const selectPatient = (e) => {
        const item = e.currentTarget;
        const id = item.dataset.id;
        const name = item.querySelector('.font-semibold')?.textContent || '';
        
        hiddenInput.value = id;
        patientInput.value = name;
        patientList.classList.remove('show');
    };

    patientInput.addEventListener('input', filterPatients);
    patientInput.addEventListener('focus', () => {
        if (patientInput.value.trim() === '') {
            patientList.classList.add('show');
        }
    });
    
    if (items) {
        items.forEach(item => item.addEventListener('click', selectPatient));
    }
    
    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (!patientInput.contains(e.target) && !patientList.contains(e.target)) {
            patientList.classList.remove('show');
        }
    });
}
