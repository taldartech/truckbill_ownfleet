import { computed, ref, watch } from 'vue';
import { listCities, listCountries, listStates } from '../api/locations';

function normalizeLocationId(id) {
    if (id === null || id === undefined || id === '') {
        return '';
    }

    const numeric = Number(id);

    return Number.isFinite(numeric) && numeric > 0 ? numeric : '';
}

export function useLocationLookups(initial = {}) {
    const countries = ref([]);
    const states = ref([]);
    const cities = ref([]);
    const loadingCountries = ref(false);
    const loadingStates = ref(false);
    const loadingCities = ref(false);
    const hydrating = ref(false);

    const countryId = ref(normalizeLocationId(initial.countryId));
    const stateId = ref(normalizeLocationId(initial.stateId));
    const cityId = ref(normalizeLocationId(initial.cityId));

    const indiaCountry = computed(() => countries.value.find((country) => country.code === 'IN') ?? countries.value[0] ?? null);

    async function loadCountries() {
        loadingCountries.value = true;

        try {
            countries.value = await listCountries();

            if (!countryId.value && indiaCountry.value) {
                countryId.value = normalizeLocationId(indiaCountry.value.id);
            }
        } finally {
            loadingCountries.value = false;
        }
    }

    async function loadStates(selectedCountryId = countryId.value) {
        if (!selectedCountryId) {
            states.value = [];
            return;
        }

        loadingStates.value = true;

        try {
            states.value = await listStates(selectedCountryId);
        } finally {
            loadingStates.value = false;
        }
    }

    async function loadCities(selectedStateId = stateId.value) {
        if (!selectedStateId) {
            cities.value = [];
            return;
        }

        loadingCities.value = true;

        try {
            cities.value = await listCities(selectedStateId);
        } finally {
            loadingCities.value = false;
        }
    }

    watch(countryId, async (value, previous) => {
        if (value === previous || hydrating.value) {
            return;
        }

        await loadStates(value);

        if (previous && value !== previous) {
            stateId.value = '';
            cityId.value = '';
            cities.value = [];
        }
    });

    watch(stateId, async (value, previous) => {
        if (value === previous || hydrating.value) {
            return;
        }

        await loadCities(value);

        if (previous && value !== previous) {
            cityId.value = '';
        }
    });

    async function initialize(options = {}) {
        hydrating.value = true;

        try {
            await loadCountries();

            const nextCountryId = normalizeLocationId(
                options.countryId ?? countryId.value ?? indiaCountry.value?.id,
            );
            countryId.value = nextCountryId;

            await loadStates(countryId.value);

            const nextStateId = normalizeLocationId(options.stateId ?? '');
            stateId.value = nextStateId;

            if (stateId.value) {
                await loadCities(stateId.value);
            } else {
                cities.value = [];
            }

            const nextCityId = normalizeLocationId(options.cityId ?? '');
            const cityExists =
                nextCityId && cities.value.some((city) => Number(city.id) === Number(nextCityId));

            cityId.value = cityExists ? nextCityId : '';
        } finally {
            hydrating.value = false;
        }
    }

    function resetLocationFields() {
        stateId.value = '';
        cityId.value = '';
        cities.value = [];
    }

    return {
        countries,
        states,
        cities,
        countryId,
        stateId,
        cityId,
        indiaCountry,
        loadingCountries,
        loadingStates,
        loadingCities,
        loadCountries,
        loadStates,
        loadCities,
        initialize,
        resetLocationFields,
    };
}
