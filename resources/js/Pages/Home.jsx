import Hero from "../Sections/Hero/Hero.jsx";
import Collection from "../Sections/Collection/Collection.jsx";

export default function Home({ catalog: _catalog }) {
  return (
    <>
      <Hero />
      <Collection />
    </>
  );
}
