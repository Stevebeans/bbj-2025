class SpoilerSwipe {
  constructor({ root = ".bbj-spoiler-bar", track = ".bbj-track", left = ".spoilerbar-vertical-left", right = ".spoilerbar-vertical-right" } = {}) {
    this.rootSel = root;
    this.trackSel = track;
    this.leftSel = left;
    this.rightSel = right;
    this.init();
  }

  init() {
    document.querySelectorAll(this.rootSel).forEach(bar => this.attach(bar));
  }

  attach(bar) {
    const track = bar.querySelector(this.trackSel);
    const left = bar.querySelector(this.leftSel);
    const right = bar.querySelector(this.rightSel);
    if (!track || !left || !right) return;

    const update = () => {
      const max = track.scrollWidth - track.clientWidth;
      if (max <= 0) {
        left.classList.add("hidden");
        right.classList.add("hidden");
        return;
      }
      const atStart = track.scrollLeft <= 1;
      const atEnd = track.scrollLeft >= max - 1;
      left.classList.toggle("hidden", atStart);
      right.classList.toggle("hidden", atEnd);
    };

    const step = () => Math.max(120, Math.round(track.clientWidth * 0.8));
    left.addEventListener("click", () => track.scrollBy({ left: -step(), behavior: "smooth" }));
    right.addEventListener("click", () => track.scrollBy({ left: step(), behavior: "smooth" }));

    track.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update);

    // If images load later / fonts swap, re-evaluate
    const ro = new ResizeObserver(update);
    ro.observe(track);

    // Initial checks
    update();
    setTimeout(update, 200);
    setTimeout(update, 800);
  }
}
export default SpoilerSwipe;
