import { Link } from "@inertiajs/react";
import { forwardRef, useRef } from "react";
import { reducedMotion } from "./StorefrontMotion.js";

const MotionLink = forwardRef(function MotionLink({ sharedRef, sharedName, onClick, onFinish, onCancel, onError, viewTransition, ...props }, ref) {
  const didShare = useRef(false);
  const clear = () => {
    if (didShare.current && sharedRef?.current) sharedRef.current.style.viewTransitionName = "";
    didShare.current = false;
  };
  const transition = viewTransition ?? (typeof document !== "undefined" && Boolean(document.startViewTransition) && !reducedMotion());
  if (props.href?.startsWith("#")) return <a {...props} ref={ref} />;
  return <Link {...props} ref={ref} viewTransition={transition} onClick={(event) => {
    if (transition && sharedRef?.current && sharedName && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
      sharedRef.current.style.viewTransitionName = sharedName;
      didShare.current = true;
    }
    onClick?.(event);
  }} onFinish={(event) => { clear(); onFinish?.(event); }} onCancel={(event) => { clear(); onCancel?.(event); }} onError={(event) => { clear(); onError?.(event); }} />;
});

export default MotionLink;
