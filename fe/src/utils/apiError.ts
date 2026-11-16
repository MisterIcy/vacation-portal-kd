import { isAxiosError } from 'axios';

export const DEFAULT_ERROR_MESSAGE = 'Something went wrong. Please try again.';
const NETWORK_ERROR_MESSAGE = 'Cannot reach the server. Check your connection and try again.';

/**
 * Turns whatever a failed request threw into a message that is safe to show:
 * the API's own `reason_phrase` when it sent one (e.g. "Email … is already in use."),
 * a connection hint when the server could not be reached, otherwise the fallback.
 */
export function getApiErrorMessage(error: unknown, fallback: string = DEFAULT_ERROR_MESSAGE): string {
  if (!isAxiosError(error)) {
    return fallback;
  }

  if (!error.response) {
    return NETWORK_ERROR_MESSAGE;
  }

  const data: unknown = error.response.data;
  if (typeof data === 'object' && data !== null && 'reason_phrase' in data) {
    const phrase = (data as { reason_phrase: unknown }).reason_phrase;
    if (typeof phrase === 'string' && phrase.trim() !== '') {
      return phrase;
    }
  }

  return fallback;
}
