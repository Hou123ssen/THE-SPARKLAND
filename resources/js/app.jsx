import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "../css/app.css";
import { CommerceProvider } from "./Components/Commerce/CommerceProvider.jsx";

createInertiaApp({
  resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob("./Pages/**/*.jsx")),
  setup({ el, App, props }) {
    createRoot(el).render(
      <StrictMode>
        <CommerceProvider><App {...props} /></CommerceProvider>
      </StrictMode>,
    );
  },
});
