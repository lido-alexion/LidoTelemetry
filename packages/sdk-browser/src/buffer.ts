import type { TelemetryEvent } from './types.js';

const DB_NAME = 'lido_telemetry_buffer';
const DB_VERSION = 1;
const STORE_NAME = 'events';

export class OfflineBuffer {
  private readonly maxSize: number;
  private db: IDBDatabase | null = null;
  private readonly openPromise: Promise<void>;

  constructor(maxSize = 500) {
    this.maxSize = maxSize;
    this.openPromise = this.open();
  }

  private open(): Promise<void> {
    if (typeof indexedDB === 'undefined') {
      return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          db.createObjectStore(STORE_NAME, { keyPath: 'event_id' });
        }
      };

      request.onsuccess = () => {
        this.db = request.result;
        resolve();
      };

      request.onerror = () => reject(request.error ?? new Error('IndexedDB open failed'));
    });
  }

  async enqueue(event: TelemetryEvent): Promise<void> {
    await this.openPromise;
    if (!this.db) {
      return;
    }

    await this.withStore('readwrite', async (store) => {
      store.put(event);
      await this.enforceLimit(store);
    });
  }

  async dequeueBatch(limit: number): Promise<TelemetryEvent[]> {
    await this.openPromise;
    if (!this.db || limit <= 0) {
      return [];
    }

    return this.withStore('readonly', async (store) => {
      const events: TelemetryEvent[] = [];
      const request = store.openCursor();

      return new Promise<TelemetryEvent[]>((resolve, reject) => {
        request.onsuccess = () => {
          const cursor = request.result;
          if (!cursor || events.length >= limit) {
            resolve(events);
            return;
          }

          events.push(cursor.value as TelemetryEvent);
          cursor.continue();
        };

        request.onerror = () => reject(request.error ?? new Error('IndexedDB cursor failed'));
      });
    });
  }

  async remove(eventIds: string[]): Promise<void> {
    await this.openPromise;
    if (!this.db || eventIds.length === 0) {
      return;
    }

    await this.withStore('readwrite', async (store) => {
      for (const id of eventIds) {
        store.delete(id);
      }
    });
  }

  async count(): Promise<number> {
    await this.openPromise;
    if (!this.db) {
      return 0;
    }

    return this.withStore('readonly', async (store) => {
      const request = store.count();
      return new Promise<number>((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error ?? new Error('IndexedDB count failed'));
      });
    });
  }

  private async enforceLimit(store: IDBObjectStore): Promise<void> {
    const countRequest = store.count();

    await new Promise<void>((resolve, reject) => {
      countRequest.onsuccess = () => {
        const count = countRequest.result;
        if (count <= this.maxSize) {
          resolve();
          return;
        }

        const toRemove = count - this.maxSize;
        let removed = 0;
        const cursor = store.openCursor();

        cursor.onsuccess = () => {
          const result = cursor.result;
          if (!result || removed >= toRemove) {
            resolve();
            return;
          }

          store.delete(result.primaryKey);
          removed += 1;
          result.continue();
        };

        cursor.onerror = () => reject(cursor.error ?? new Error('IndexedDB trim failed'));
      };

      countRequest.onerror = () => reject(countRequest.error ?? new Error('IndexedDB count failed'));
    });
  }

  private async withStore<T>(
    mode: IDBTransactionMode,
    fn: (store: IDBObjectStore) => Promise<T>,
  ): Promise<T> {
    if (!this.db) {
      return fn(null as unknown as IDBObjectStore);
    }

    return new Promise<T>((resolve, reject) => {
      const tx = this.db!.transaction(STORE_NAME, mode);
      const store = tx.objectStore(STORE_NAME);

      fn(store)
        .then(resolve)
        .catch(reject);

      tx.onerror = () => reject(tx.error ?? new Error('IndexedDB transaction failed'));
    });
  }
}
