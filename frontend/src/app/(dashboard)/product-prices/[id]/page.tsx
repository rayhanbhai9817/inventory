import { ProductPriceHistoryView } from "./ProductPriceHistoryView";

export default async function ProductPriceHistoryPage(props: PageProps<"/product-prices/[id]">) {
  const { id } = await props.params;

  return <ProductPriceHistoryView productId={Number(id)} />;
}
