/**
 * What this browser knows about passkeys on this device. Nothing here reaches the server: a
 * passkey belongs to a device, so a decline or a registration is remembered per browser.
 */

const OFFER_KEY = 'arcadia.passkey.offer';
const REGISTERED_KEY = 'arcadia.passkey.registered';
const SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;

/** "Safari · iPhone" — a name the reader recognises later in Settings › Security. */
export const defaultPasskeyName = (): string => {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' · ') || '';
};

/** A built-in authenticator — Face ID, a fingerprint, Windows Hello — not just WebAuthn. */
export const canHoldPasskey = async (): Promise<boolean> => {
    try {
        return await window.PublicKeyCredential?.isUserVerifyingPlatformAuthenticatorAvailable?.() === true;
    } catch {
        return false;
    }
};

const read = (key: string): string | null => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};

const write = (key: string, value: string): void => {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Private mode or blocked storage: the offer simply comes back next time.
    }
};

/** "Never" is for good; "not now" waits a week. */
export const offerDeclined = (): boolean => {
    const declined = read(OFFER_KEY);

    return declined === 'never' || (declined !== null && Number(declined) > Date.now());
};

export const declineOffer = (forGood: boolean): void => write(OFFER_KEY, forGood ? 'never' : String(Date.now() + SNOOZE_MS));

/** This browser made a passkey, so `/login` puts the passkey first here. */
export const rememberPasskeyRegistered = (): void => write(REGISTERED_KEY, '1');

export const passkeyRegisteredHere = (): boolean => read(REGISTERED_KEY) === '1';
