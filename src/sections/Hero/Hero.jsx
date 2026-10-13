import { useLayoutEffect, useRef } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import Lenis from "lenis";
import "lenis/dist/lenis.css";
import Navbar from "../../components/Navbar.jsx";

gsap.registerPlugin(ScrollTrigger);

const sequenceFramePaths = [
  "/images/hero-sequence/ezgif-frame-001.png",
  "/images/hero-sequence/ezgif-frame-002.png",
  "/images/hero-sequence/ezgif-frame-003.png",
  "/images/hero-sequence/ezgif-frame-004.png",
  "/images/hero-sequence/ezgif-frame-005.png",
  "/images/hero-sequence/ezgif-frame-006.png",
  "/images/hero-sequence/ezgif-frame-007.png",
  "/images/hero-sequence/ezgif-frame-008.png",
  "/images/hero-sequence/ezgif-frame-009.png",
  "/images/hero-sequence/ezgif-frame-010.png",
  "/images/hero-sequence/ezgif-frame-011.png",
  "/images/hero-sequence/ezgif-frame-012.png",
  "/images/hero-sequence/ezgif-frame-013.png",
  "/images/hero-sequence/ezgif-frame-014.png",
  "/images/hero-sequence/ezgif-frame-015.png",
  "/images/hero-sequence/ezgif-frame-016.png",
  "/images/hero-sequence/ezgif-frame-017.png",
  "/images/hero-sequence/ezgif-frame-018.png",
  "/images/hero-sequence/ezgif-frame-019.png",
  "/images/hero-sequence/ezgif-frame-020.png",
  "/images/hero-sequence/ezgif-frame-021.png",
  "/images/hero-sequence/ezgif-frame-022.png",
  "/images/hero-sequence/ezgif-frame-023.png",
  "/images/hero-sequence/ezgif-frame-024.png",
  "/images/hero-sequence/ezgif-frame-025.png",
  "/images/hero-sequence/ezgif-frame-026.png",
  "/images/hero-sequence/ezgif-frame-027.png",
  "/images/hero-sequence/ezgif-frame-028.png",
  "/images/hero-sequence/ezgif-frame-029.png",
  "/images/hero-sequence/ezgif-frame-030.png",
  "/images/hero-sequence/ezgif-frame-031.png",
  "/images/hero-sequence/ezgif-frame-032.png",
  "/images/hero-sequence/ezgif-frame-033.png",
  "/images/hero-sequence/ezgif-frame-034.png",
  "/images/hero-sequence/ezgif-frame-035.png",
  "/images/hero-sequence/ezgif-frame-036.png",
  "/images/hero-sequence/ezgif-frame-037.png",
  "/images/hero-sequence/ezgif-frame-038.png",
  "/images/hero-sequence/ezgif-frame-039.png",
  "/images/hero-sequence/ezgif-frame-040.png",
  "/images/hero-sequence/ezgif-frame-041.png",
  "/images/hero-sequence/ezgif-frame-042.png",
  "/images/hero-sequence/ezgif-frame-043.png",
  "/images/hero-sequence/ezgif-frame-044.png",
  "/images/hero-sequence/ezgif-frame-045.png",
  "/images/hero-sequence/ezgif-frame-046.png",
  "/images/hero-sequence/ezgif-frame-047.png",
  "/images/hero-sequence/ezgif-frame-048.png",
  "/images/hero-sequence/ezgif-frame-049.png",
  "/images/hero-sequence/ezgif-frame-050.png",
  "/images/hero-sequence/ezgif-frame-051.png",
  "/images/hero-sequence/ezgif-frame-052.png",
  "/images/hero-sequence/ezgif-frame-053.png",
  "/images/hero-sequence/ezgif-frame-054.png",
  "/images/hero-sequence/ezgif-frame-055.png",
  "/images/hero-sequence/ezgif-frame-056.png",
  "/images/hero-sequence/ezgif-frame-057.png",
  "/images/hero-sequence/ezgif-frame-058.png",
  "/images/hero-sequence/ezgif-frame-059.png",
  "/images/hero-sequence/ezgif-frame-060.png",
  "/images/hero-sequence/ezgif-frame-061.png",
  "/images/hero-sequence/ezgif-frame-062.png",
  "/images/hero-sequence/ezgif-frame-063.png",
  "/images/hero-sequence/ezgif-frame-064.png",
  "/images/hero-sequence/ezgif-frame-065.png",
  "/images/hero-sequence/ezgif-frame-066.png",
  "/images/hero-sequence/ezgif-frame-067.png",
  "/images/hero-sequence/ezgif-frame-068.png",
  "/images/hero-sequence/ezgif-frame-069.png",
  "/images/hero-sequence/ezgif-frame-070.png",
  "/images/hero-sequence/ezgif-frame-071.png",
  "/images/hero-sequence/ezgif-frame-072.png",
  "/images/hero-sequence/ezgif-frame-073.png",
  "/images/hero-sequence/ezgif-frame-074.png",
  "/images/hero-sequence/ezgif-frame-075.png",
  "/images/hero-sequence/ezgif-frame-076.png",
  "/images/hero-sequence/ezgif-frame-077.png",
  "/images/hero-sequence/ezgif-frame-078.png",
  "/images/hero-sequence/ezgif-frame-079.png",
  "/images/hero-sequence/ezgif-frame-080.png",
];

function Hero() {
  const heroSceneRef = useRef(null);
  const heroStageRef = useRef(null);
  const heroSequenceCanvasRef = useRef(null);
  const heroBackgroundTitleRef = useRef(null);
  const heroTitleTheRef = useRef(null);
  const heroTitleSparklandRef = useRef(null);
  const heroLeftContentRef = useRef(null);
  const heroRightContentRef = useRef(null);
  const heroScrollCueRef = useRef(null);

  useLayoutEffect(() => {
    const scene = heroSceneRef.current;
    const canvas = heroSequenceCanvasRef.current;
    const title = heroBackgroundTitleRef.current;
    const titleThe = heroTitleTheRef.current;
    const titleSparkland = heroTitleSparklandRef.current;
    const left = heroLeftContentRef.current;
    const right = heroRightContentRef.current;
    const cue = heroScrollCueRef.current;
    const context = canvas.getContext("2d");
    const frameImages = sequenceFramePaths.map(() => new Image());
    const sequence = { frame: 0 };
    const openingVerticalOffset = { value: 0 };
    const openingScale = { value: 1 };
    let disposed = false;
    let drawQueued = false;

    const drawFrame = () => {
      drawQueued = false;
      const image = frameImages[Math.round(sequence.frame)];
      if (disposed || !image?.complete || !image.naturalWidth) return;

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

      const frame = Math.round(sequence.frame);
      const scale = Math.max(bounds.width / image.naturalWidth, bounds.height / image.naturalHeight);
      const openingProgress = Math.min(frame / 18, 1);
      const scaleFactor = openingScale.value + (1 - openingScale.value) * openingProgress;
      const composedScale = scale * scaleFactor;
      const drawWidth = image.naturalWidth * composedScale;
      const drawHeight = image.naturalHeight * composedScale;
      const openingOffset = openingVerticalOffset.value * (1 - openingProgress);
      context.drawImage(
        image,
        (bounds.width - drawWidth) / 2,
        (bounds.height - drawHeight) / 2 + openingOffset,
        drawWidth,
        drawHeight,
      );
    };

    const requestDraw = () => {
      if (drawQueued) return;
      drawQueued = true;
      requestAnimationFrame(drawFrame);
    };

    const resizeObserver = new ResizeObserver(requestDraw);
    resizeObserver.observe(canvas);

    frameImages.forEach((image, index) => {
      image.onload = () => {
        if (index === 0 || index === Math.round(sequence.frame)) requestDraw();
      };
      image.src = sequenceFramePaths[index];
    });

    const lenis = new Lenis({ autoRaf: false, lerp: 0.1 });
    const updateScrollTrigger = () => ScrollTrigger.update();
    const tick = (time) => lenis.raf(time * 1000);
    lenis.on("scroll", updateScrollTrigger);
    gsap.ticker.add(tick);

    const media = gsap.matchMedia();
    media.add(
      {
        desktop: "(min-width: 1024px)",
        tablet: "(min-width: 640px) and (max-width: 1023px)",
        mobile: "(max-width: 639px)",
      },
      ({ conditions }) => {
        const { desktop, tablet, mobile } = conditions;
        const contentShift = desktop ? 20 : mobile ? 12 : 16;
        openingVerticalOffset.value = desktop ? 68 : tablet ? 42 : 24;
        openingScale.value = desktop ? 0.9 : tablet ? 0.93 : 0.95;
        const timeline = gsap.timeline({
          defaults: { ease: "power2.inOut" },
          scrollTrigger: {
          trigger: scene,
          start: "top top",
            end: "bottom bottom",
            pin: false,
            scrub: 1,
            anticipatePin: 1,
            invalidateOnRefresh: true,
          },
        });

        // Timeline positions are percentages of the full pinned scroll distance.
        timeline
          .to(sequence, { frame: sequenceFramePaths.length - 1, duration: 100, ease: "none", onUpdate: requestDraw }, 0)
          .to(cue, { autoAlpha: 0, duration: 9 }, 10)
          .to(left, { opacity: 0.68, x: -contentShift, duration: 21 }, 17)
          .to(right, { opacity: 0.68, x: contentShift, duration: 21 }, 17)
          .to(title, { yPercent: 16, duration: 48, ease: "none" }, 18)
          .to(titleThe, { opacity: 0.1, duration: 48, ease: "none" }, 18)
          .to(titleSparkland, { opacity: 0.09, duration: 48, ease: "none" }, 18)
          .to(left, { opacity: 0.82, duration: 14 }, 68)
          .to(right, { opacity: 0.82, duration: 14 }, 68)
          .to(title, { yPercent: 28, duration: 32, ease: "none" }, 68)
          .to(titleThe, { opacity: 0.09, duration: 32, ease: "none" }, 68)
          .to(titleSparkland, { opacity: 0.08, duration: 32, ease: "none" }, 68);
      },
    );

    ScrollTrigger.refresh();

    return () => {
      media.revert();
      disposed = true;
      resizeObserver.disconnect();
      frameImages.forEach((image) => {
        image.onload = null;
        image.src = "";
      });
      gsap.ticker.remove(tick);
      lenis.off("scroll", updateScrollTrigger);
      lenis.destroy();
    };
  }, []);

  return (
    <>
      <section ref={heroSceneRef} className="relative h-[300svh] bg-white lg:h-[330svh]">
        <Navbar />

        <div
          ref={heroStageRef}
          className="hero-editorial-stage sticky top-[76px] isolate h-[calc(100svh-76px)] overflow-hidden text-[#111111]"
        >
          <div
            ref={heroBackgroundTitleRef}
            className="pointer-events-none absolute left-1/2 top-[17vh] z-[1] w-full -translate-x-1/2 text-center font-headline font-black uppercase leading-[0.8] tracking-[-0.05em] text-[#111111] sm:top-[14vh] lg:top-[calc(12vh-42px)]"
            aria-hidden="true"
          >
            <span ref={heroTitleTheRef} className="block -translate-y-[25px] text-[clamp(5.2rem,15.5vw,15.8rem)] font-black opacity-[0.23]">The</span>
            <span ref={heroTitleSparklandRef} className="block text-[clamp(4.7rem,13.2vw,14rem)] font-black opacity-[0.19]">Sparkland</span>
          </div>

          <canvas
            ref={heroSequenceCanvasRef}
            className="pointer-events-none absolute inset-0 z-10 h-full w-full mix-blend-normal opacity-100"
            aria-label="The Sparkland cinematic eyewear sequence"
            role="img"
          />

          <div className="relative z-10 mx-auto flex h-full max-w-[1440px] flex-col px-6 pt-12 sm:px-8 sm:pt-14 lg:block lg:px-12 lg:pt-0 xl:px-16">
            <div ref={heroLeftContentRef} className="relative z-20 max-w-[13rem] text-[#111111] lg:absolute lg:left-12 lg:top-[15vh] xl:left-14">
              <p className="text-[0.68rem] font-bold uppercase tracking-[0.18em]">SS26</p>
              <p className="mt-3 text-[0.58rem] uppercase leading-[1.65] tracking-[0.25em] text-neutral-500">
                Eyewear<br />For a brighter<br />Tomorrow.
              </p>
              <span className="mt-5 block h-px w-8 bg-[#111111]" aria-hidden="true" />
            </div>

            <div ref={heroRightContentRef} className="absolute right-6 top-[15vh] z-20 hidden w-[13rem] text-right text-[#111111] sm:right-8 sm:block lg:right-12 xl:right-14">
              <p className="text-[0.68rem] font-bold uppercase tracking-[0.18em]">Global</p>
              <p className="mt-3 text-[0.58rem] uppercase leading-[1.65] tracking-[0.25em] text-neutral-500">
                Modern icons<br />Timeless vision<br />Anywhere.
              </p>
              <span className="ml-auto mt-5 block h-px w-8 bg-[#111111]" aria-hidden="true" />
            </div>

            <div className="absolute bottom-[10vh] left-6 z-20 hidden text-[#111111] sm:block lg:left-12 xl:left-14">
              <p className="text-[0.58rem] uppercase tracking-[0.22em] text-neutral-500">01 &mdash;&mdash;</p>
              <p className="mt-3 font-sans text-[clamp(1.7rem,2.4vw,2.625rem)] font-normal uppercase leading-[1.05] tracking-[0.06em]">
                A brighter<br />Perspective
              </p>
              <p className="mt-4 text-[0.55rem] uppercase leading-[1.7] tracking-[0.22em] text-neutral-500">
                Precision eyewear<br />For a more<br />Human tomorrow.
              </p>
            </div>

            <div className="absolute bottom-[10vh] right-6 z-20 hidden text-right text-[#111111] sm:block lg:right-12 xl:right-14">
              <p className="text-[0.58rem] uppercase tracking-[0.22em] text-neutral-500">02 &mdash;&mdash;</p>
              <p className="mt-3 font-sans text-[clamp(1.7rem,2.4vw,2.625rem)] font-normal uppercase leading-[1.05] tracking-[0.06em]">
                Shaped<br />By light
              </p>
              <p className="mt-4 text-[0.55rem] uppercase leading-[1.7] tracking-[0.22em] text-neutral-500">
                Clean lines.<br />Bolder days.<br />The Sparkland.
              </p>
            </div>

            <div
              ref={heroScrollCueRef}
              className="absolute inset-x-0 bottom-0 z-40 flex flex-col items-center gap-3 pb-[10px] text-center"
            >
              <span className="text-[0.58rem] font-medium uppercase tracking-[0.28em] text-neutral-600">
                Scroll to Explore
              </span>
              <span className="h-8 w-px bg-neutral-500" aria-hidden="true" />
            </div>
          </div>

        </div>
      </section>
    </>
  );
}

export default Hero;
