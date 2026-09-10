import type { Metadata } from './types.js';

export interface ContextLayers {
  application: Metadata;
  session: Metadata;
  view: Metadata;
}

export class ContextManager {
  private layers: ContextLayers = {
    application: {},
    session: {},
    view: {},
  };

  setApplicationContext(metadata: Metadata): void {
    this.layers.application = { ...metadata };
  }

  mergeApplicationContext(metadata: Metadata): void {
    this.layers.application = { ...this.layers.application, ...metadata };
  }

  setSessionContext(metadata: Metadata): void {
    this.layers.session = { ...metadata };
  }

  mergeSessionContext(metadata: Metadata): void {
    this.layers.session = { ...this.layers.session, ...metadata };
  }

  setViewContext(metadata: Metadata): void {
    this.layers.view = { ...metadata };
  }

  mergeViewContext(metadata: Metadata): void {
    this.layers.view = { ...this.layers.view, ...metadata };
  }

  clearViewContext(): void {
    this.layers.view = {};
  }

  /** application → session → view → event (more specific wins). */
  resolve(eventMetadata?: Metadata): Metadata {
    return {
      ...this.layers.application,
      ...this.layers.session,
      ...this.layers.view,
      ...(eventMetadata ?? {}),
    };
  }

  getLayers(): Readonly<ContextLayers> {
    return this.layers;
  }
}
