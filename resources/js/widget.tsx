/**
 * Teleman Softphone Widget - Embeddable Script
 * 
 * This is a standalone build that can be embedded in any website.
 * 
 * Usage — the stylesheet is a separate file (the library build extracts
 * it), so a page that loads only the script gets an unstyled widget:
 * <link rel="stylesheet" href="https://your-domain.com/widget/softphone.css">
 * <script src="https://your-domain.com/widget/softphone.umd.js"></script>
 * <script>
 *   TelemanSoftphone.init({
 *     apiKey: 'pk_live_xxxxx',
 *     phoneNumber: '+1234567890',
 *     position: 'bottom-right',
 *     theme: 'light'
 *   });
 * </script>
 */

import React, { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { WidgetSoftphoneProvider } from '@/contexts/WidgetSoftphoneContext';
import { WidgetSoftphoneWidget } from '@/components/softphone/WidgetSoftphoneWidget';
import '../css/app.css';

interface WidgetConfig {
  apiKey: string;
  phoneNumber?: string;
  position?: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left';
  theme?: 'light' | 'dark' | 'auto';
  language?: string;
  primaryColor?: string;
  callbacks?: {
    onReady?: () => void;
    onCallStarted?: (callId: string) => void;
    onCallEnded?: (callId: string, duration: number) => void;
    onError?: (error: string) => void;
  };
}

class TelemanSoftphoneWidget {
  private config: WidgetConfig | null = null;
  private container: HTMLDivElement | null = null;
  private root: any = null;
  private shadowRoot: ShadowRoot | null = null;

  /**
   * Initialize the widget
   */
  init(config: WidgetConfig): void {
    if (!config.apiKey) {
      console.error('[Teleman Softphone] API key is required');
      return;
    }

    this.config = config;
    console.log('[Teleman Softphone] Initializing widget...', {
      position: config.position || 'bottom-right',
      theme: config.theme || 'auto',
    });

    // Validate API key first
    this.validateApiKey().then((valid) => {
      if (valid) {
        this.createWidget();
        if (config.callbacks?.onReady) {
          config.callbacks.onReady();
        }
      } else {
        console.error('[Teleman Softphone] Invalid API key');
        if (config.callbacks?.onError) {
          config.callbacks.onError('Invalid API key');
        }
      }
    });
  }

  /**
   * Validate API key with backend
   */
  private async validateApiKey(): Promise<boolean> {
    if (!this.config) return false;

    try {
      const response = await fetch(`${this.getApiUrl()}/api/widget/validate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          api_key: this.config.apiKey,
          domain: window.location.hostname,
        }),
      });

      const data = await response.json();
      return data.valid === true;
    } catch (error) {
      console.error('[Teleman Softphone] API key validation failed:', error);
      return false;
    }
  }

  /**
   * Create the widget container and render
   */
  private createWidget(): void {
    if (!this.config) return;

    // Create container div
    this.container = document.createElement('div');
    this.container.id = 'teleman-softphone-widget';
    this.container.style.cssText = 'position: fixed; z-index: 999999;';

    // Apply theme
    if (this.config.theme) {
      this.container.setAttribute('data-theme', this.config.theme);
    }

    // Create shadow DOM for style isolation
    this.shadowRoot = this.container.attachShadow({ mode: 'open' });

    // Create style container
    const styleContainer = document.createElement('div');
    styleContainer.id = 'teleman-softphone-root';
    this.shadowRoot.appendChild(styleContainer);

    // Inject styles (you'll need to include compiled CSS)
    const style = document.createElement('style');
    style.textContent = this.getWidgetStyles();
    this.shadowRoot.appendChild(style);

    // Append to body
    document.body.appendChild(this.container);

    // Render React component
    this.root = createRoot(styleContainer);
    this.root.render(
      <StrictMode>
        <WidgetSoftphoneProvider apiKey={this.config.apiKey}>
          <WidgetSoftphoneWidget />
        </WidgetSoftphoneProvider>
      </StrictMode>
    );

    console.log('[Teleman Softphone] Widget initialized successfully');
  }

  /**
   * Get API URL based on environment
   */
  private getApiUrl(): string {
    // In production, this would be your actual API domain
    // For now, we'll use the current domain
    return window.location.origin;
  }

  /**
   * Get widget styles (compiled CSS)
   */
  private getWidgetStyles(): string {
    // This will be replaced with actual compiled CSS in the build process
    return `
      /* Tailwind base styles and component styles will be injected here during build */
      :host {
        all: initial;
        font-family: system-ui, -apple-system, sans-serif;
      }
      * {
        box-sizing: border-box;
      }
    `;
  }

  /**
   * Destroy the widget
   */
  destroy(): void {
    if (this.root) {
      this.root.unmount();
      this.root = null;
    }
    if (this.container && this.container.parentNode) {
      this.container.parentNode.removeChild(this.container);
      this.container = null;
    }
    this.config = null;
    console.log('[Teleman Softphone] Widget destroyed');
  }

  /**
   * Update widget configuration
   */
  updateConfig(config: Partial<WidgetConfig>): void {
    if (this.config) {
      this.config = { ...this.config, ...config };
      console.log('[Teleman Softphone] Configuration updated', config);
    }
  }
}

// Create global instance
const widgetInstance = new TelemanSoftphoneWidget();

// Expose to window
declare global {
  interface Window {
    TelemanSoftphone: typeof widgetInstance;
  }
}

window.TelemanSoftphone = widgetInstance;

// Export for module usage
export default widgetInstance;
