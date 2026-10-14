import { useLayoutEffect, useRef } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);

const framePaths = Array.from(
  { length: 80 },
  (_, index) => index === 79
    ? "/images/collection-sequence/frame-080.png.png"
    : `/images/collection-sequence/frame-${String(index + 1).padStart(3, "0")}.png`,
);

function Collection() {
  const sectionRef = useRef(null);
  const stageRef = useRef(null);
  const canvasRef = useRef(null);
  const titleRef = useRef(null);

  useLayoutEffect(() => {
    const section = sectionRef.current;
    const stage = stageRef.current;
    const canvas = canvasRef.current;
    const title = titleRef.current;
    const context = canvas.getContext("2d");
    const images = Array(framePaths.length).fill(null);
    const loadTimers = [];
    const sequence = { frame: 0 };
    let disposed = false;
    let drawQueued = false;
    let currentFrame = 0;

    const drawFrame = () => {
      drawQueued = false;
      if (disposed) return;

      const requested = Math.max(0, Math.min(framePaths.length - 1, Math.round(sequence.frame)));
      let image = images[requested];
      if (!image?.complete || !image.naturalWidth) {
        for (let distance = 1; distance < framePaths.length; distance += 1) {
          const before = images[requested - distance];
          const after = images[requested + distance];
          image = before?.complete && before.naturalWidth ? before : after;
          if (image?.complete && image.naturalWidth) break;
        }
      }
      if (!image?.complete || !image.naturalWidth) return;

      const bounds = canvas.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const width = Math.max(1, Math.round(bounds.width * dpr));
      const height = Math.max(1, Math.round(bounds.height * dpr));
      if (canvas.width !== width || canvas.height !== height) {
        canvas.width = width;
        canvas.height = height;
      }

      context.setTransform(dpr, 0, 0, dpr, 0, 0);
      context.clearRect(0, 0, bounds.width, bounds.height);
      const scale = Math.min(bounds.width / image.naturalWidth, bounds.height / image.naturalHeight);
      const drawWidth = image.naturalWidth * scale;
      const drawHeight = image.naturalHeight * scale;
      context.drawImage(
        image,
        (bounds.width - drawWidth) / 2,
        (bounds.height - drawHeight) / 2,
        drawWidth,
        drawHeight,
      );
      currentFrame = requested;
    };

    const requestDraw = () => {
      if (drawQueued) return;
      drawQueued = true;
      requestAnimationFrame(drawFrame);
    };

    const loadFrame = (index) => {
      if (disposed || images[index]) return;
      const image = new Image();
      image.decoding = "async";
      image.onload = () => {
        if (disposed) return;
        images[index] = image;
        if (index === 0 || index === Math.round(sequence.frame) || currentFrame === index) requestDraw();
        if (index === 0) ScrollTrigger.refresh();
      };
      image.src = framePaths[index];
    };

    const preloadNextBatch = (start, end, delay) => {
      loadTimers.push(
        window.setTimeout(() => {
          for (let index = start; index < end; index += 1) loadFrame(index);
        }, delay),
      );
    };

    for (let index = 0; index < 6; index += 1) loadFrame(index);
    preloadNextBatch(6, 20, 250);
    preloadNextBatch(20, 42, 900);
    preloadNextBatch(42, 64, 1600);
    preloadNextBatch(64, framePaths.length, 2300);

    const resizeObserver = new ResizeObserver(requestDraw);
    resizeObserver.observe(canvas);

    const timeline = gsap.timeline({
      defaults: { ease: "none" },
      scrollTrigger: {
        trigger: section,
        start: "top top",
        end: "bottom bottom",
        scrub: 1,
        invalidateOnRefresh: true,
      },
    });

    timeline
      .to(sequence, {
        frame: framePaths.length - 1,
        duration: 100,
        onUpdate: requestDraw,
      }, 0)
      .fromTo(
        title,
        { y: 100, opacity: 0, scale: 0.96 },
        { y: 0, opacity: 1, scale: 1, duration: 100 },
        0,
      );

    ScrollTrigger.refresh();

    return () => {
      disposed = true;
      resizeObserver.disconnect();
      loadTimers.forEach((timer) => window.clearTimeout(timer));
      timeline.scrollTrigger?.kill();
      timeline.kill();
      images.forEach((image) => {
        if (image) image.onload = null;
      });
    };
  }, []);

  return (
    <section ref={sectionRef} className="relative h-[280svh] bg-white">
      <div
        ref={stageRef}
        className="sticky top-[76px] isolate h-[calc(100svh-76px)] overflow-hidden bg-white text-[#111111]"
      >
        <div
          ref={titleRef}
          className="pointer-events-none absolute left-1/2 top-[calc(50%-170px)] z-[1] w-full -translate-x-1/2 -translate-y-1/2 bg-clip-text text-center font-headline text-[clamp(5.5rem,14vw,14rem)] font-black uppercase leading-[0.8] tracking-[-0.055em] text-transparent sm:top-[calc(50%-190px)] md:top-[calc(50%-250px)] lg:top-[20px] xl:top-[20px]"
          style={{ backgroundImage: "linear-gradient(to bottom, rgb(0 87 200 / 1) 0%, rgb(0 87 200 / 0.92) 45%, rgb(0 87 200 / 0.45) 100%)" }}
          aria-hidden="true"
        >
          Collection
        </div>

        <canvas
          ref={canvasRef}
          className="pointer-events-none absolute inset-0 z-10 h-full w-full"
          aria-label="The Sparkland collection unfolding sequence"
          role="img"
        />
      </div>
    </section>
  );
}

export default Collection;
