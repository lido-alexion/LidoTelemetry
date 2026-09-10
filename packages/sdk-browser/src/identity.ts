const ANONYMOUS_ID_KEY = 'lido_telemetry_anonymous_id';
const SESSION_ID_KEY = 'lido_telemetry_session_id';

function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const rand = (Math.random() * 16) | 0;
    const value = char === 'x' ? rand : (rand & 0x3) | 0x8;
    return value.toString(16);
  });
}

/** Persistent anonymous identity across browser restarts (localStorage). */
export function getOrCreateAnonymousId(): string {
  try {
    const existing = localStorage.getItem(ANONYMOUS_ID_KEY);
    if (existing) {
      return existing;
    }

    const id = generateId();
    localStorage.setItem(ANONYMOUS_ID_KEY, id);
    return id;
  } catch {
    return generateId();
  }
}

/** Per-tab session identity (sessionStorage). */
export function getOrCreateSessionId(): string {
  try {
    const existing = sessionStorage.getItem(SESSION_ID_KEY);
    if (existing) {
      return existing;
    }

    const id = generateId();
    sessionStorage.setItem(SESSION_ID_KEY, id);
    return id;
  } catch {
    return generateId();
  }
}

export { generateId };
