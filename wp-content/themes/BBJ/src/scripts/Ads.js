class AdDisplay {
  constructor() {
    this.headerAdContainer = document.querySelector("#ad-container-header");

    if (this.headerAdContainer) {
      this.setHeaderAd();
    }
  }

  setHeaderAd() {
    const screenWidth = window.innerWidth;
    let adHTML = "";

    if (screenWidth >= 768) {
      // For desktop
      console.log("desktop");
      adHTML = `
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1172879704296990"
          crossorigin="anonymous"></script>
        <!-- Your Desktop AdSense Code -->
        <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-1172879704296990" data-ad-slot="2494556659"></ins>
        <script>
          (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
      `;
    } else {
      console.log("mobile");
      // For mobile
      adHTML = `
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1172879704296990"
          crossorigin="anonymous"></script>
        <!-- Your Mobile AdSense Code -->
        <ins class="adsbygoogle" style="display:inline-block;width:300px;height:100px" data-ad-client="ca-pub-1172879704296990" data-ad-slot="1392930221"></ins>
        <script>
          (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
      `;
    }

    this.headerAdContainer.innerHTML = adHTML;
    (adsbygoogle = window.adsbygoogle || []).push({});
  }
}

export default AdDisplay;
