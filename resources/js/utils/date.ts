export function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const [year, month, day] = iso.split('-');

    if (!day || !month || !year) {
        return iso;
    }

    return `${day}/${month}/${year}`;
}
